<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Services\NodeApi\NodeApiSigner;
use App\Services\NodeApi\NodeApiAuthenticator;
use App\Services\NodeApi\NodeNonceStore;
use App\Services\NodeApi\NodeIdempotencyStore;
use App\Services\NodeApi\NodeCredentialManager;
use App\Services\NodeApi\NodeRequestValidator;

final class NodeApiAuthTest extends TestCase
{
    private Database $db;
    private int $nodeId;
    private string $secret;
    private array $config;

    protected function setUp(): void
    {
        $base = dirname(__DIR__,2);
        if (file_exists($base.'/.env')) { \Dotenv\Dotenv::createImmutable($base)->load(); }
        $cfg = require $base.'/config/database.php';
        $cfg['database'] = 'freehost_manager';
        Database::reset();
        $this->db = Database::getInstance($cfg);
        $this->config = require $base.'/config/node-api.php';
        // Ensure clean node
        $this->db->execute("DELETE FROM node_api_nonces WHERE node_id IN (SELECT id FROM hosting_nodes WHERE name LIKE 'test-node-%')");
        $this->db->execute("DELETE FROM node_api_idempotency WHERE node_id IN (SELECT id FROM hosting_nodes WHERE name LIKE 'test-node-%')");
        $this->db->execute("DELETE FROM hosting_nodes WHERE name LIKE 'test-node-%'");
        $this->secret = NodeCredentialManager::generateSecret();
        $node = $this->db->fetch("SELECT id FROM hosting_nodes WHERE name='test-node-auth'");
        if (!$node) {
            $this->db->query("INSERT INTO hosting_nodes (name,hostname,status,max_accounts,api_url) VALUES (?,?,?,?,?)", ['test-node-auth','test-auth.example.com','active',100,'http://localhost/mock-provisioning']);
            $this->nodeId = (int)$this->db->lastInsertId();
        } else { $this->nodeId = (int)$node['id']; }
        NodeCredentialManager::store($this->db, $this->nodeId, $this->secret);
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM node_api_nonces WHERE node_id=?", [$this->nodeId]);
        $this->db->execute("DELETE FROM node_api_idempotency WHERE node_id=?", [$this->nodeId]);
        $this->db->execute("DELETE FROM hosting_nodes WHERE id=?", [$this->nodeId]);
        Database::reset();
    }

    private function auth(string $method, string $path, string $body, array $headers): array
    {
        $auth = new NodeApiAuthenticator($this->db, new NodeNonceStore($this->db), new NodeIdempotencyStore($this->db), $this->config);
        return $auth->authenticate($method, $path, $body, $headers);
    }

    private function validHeaders(string $method, string $path, string $body, ?string $idem=null, ?string $req=null, ?string $nonce=null, ?int $ts=null): array
    {
        $tsStr = (string)($ts ?? time());
        $nonceStr = $nonce ?? bin2hex(random_bytes(16));
        $reqStr = $req ?? bin2hex(random_bytes(8));
        $idemStr = $idem ?? bin2hex(random_bytes(16));
        $sig = NodeApiSigner::sign($this->secret, $method, $path, $tsStr, $nonceStr, $reqStr, $idemStr, $body);
        return [
            NodeApiSigner::HEADER_NODE_ID => (string)$this->nodeId,
            NodeApiSigner::HEADER_TIMESTAMP => $tsStr,
            NodeApiSigner::HEADER_NONCE => $nonceStr,
            NodeApiSigner::HEADER_REQUEST_ID => $reqStr,
            NodeApiSigner::HEADER_IDEMPOTENCY_KEY => $idemStr,
            NodeApiSigner::HEADER_SIGNATURE => $sig,
        ];
    }

    public function testValidSignatureAccepted(): void
    {
        $body='{"operation":"hosting.create_identity","payload":{"account_id":1}}';
        $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body);
        $r=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h);
        $this->assertTrue($r['ok']);
        $this->assertFalse($r['idempotent_replay']);
    }
    public function testInvalidSignatureRejected(): void
    {
        $body='{}'; $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body);
        $h[NodeApiSigner::HEADER_SIGNATURE]='0'.substr($h[NodeApiSigner::HEADER_SIGNATURE],1);
        $r=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h);
        $this->assertFalse($r['ok']);
    }
    public function testModifiedBodyRejected(): void
    {
        $body='{"a":1}'; $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body);
        $r=$this->auth('POST','/v1/node/hosting/create-identity','{"a":2}',$h);
        $this->assertFalse($r['ok']);
    }
    public function testModifiedPathRejected(): void
    {
        $body='{}'; $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body);
        $r=$this->auth('POST','/v1/node/hosting/suspend',$body,$h);
        $this->assertFalse($r['ok']);
    }
    public function testModifiedMethodRejected(): void
    {
        $body='{}'; $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body);
        $r=$this->auth('GET','/v1/node/hosting/create-identity',$body,$h);
        $this->assertFalse($r['ok']);
    }
    public function testMissingHeadersRejected(): void
    {
        $r=$this->auth('POST','/v1/node/hosting/create-identity','{}',[]);
        $this->assertFalse($r['ok']); $this->assertEquals(401,$r['http']);
    }
    public function testExpiredTimestampRejected(): void
    {
        $body='{}'; $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body,null,null,null,time()-600);
        $r=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h);
        $this->assertFalse($r['ok']);
    }
    public function testFutureTimestampRejected(): void
    {
        $body='{}'; $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body,null,null,null,time()+600);
        $r=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h);
        $this->assertFalse($r['ok']);
    }
    public function testReplayNonceRejected(): void
    {
        $body='{}'; $nonce=bin2hex(random_bytes(16)); $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body,null,null,$nonce);
        $r1=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h);
        $this->assertTrue($r1['ok']);
        // second with same nonce but fresh signature (same nonce)
        $ts=(string)time(); $req=bin2hex(random_bytes(8)); $idem=bin2hex(random_bytes(16));
        $sig=NodeApiSigner::sign($this->secret,'POST','/v1/node/hosting/create-identity',$ts,$nonce,$req,$idem,$body);
        $h2=[NodeApiSigner::HEADER_NODE_ID=>(string)$this->nodeId, NodeApiSigner::HEADER_TIMESTAMP=>$ts, NodeApiSigner::HEADER_NONCE=>$nonce, NodeApiSigner::HEADER_REQUEST_ID=>$req, NodeApiSigner::HEADER_IDEMPOTENCY_KEY=>$idem, NodeApiSigner::HEADER_SIGNATURE=>$sig];
        $r2=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h2);
        $this->assertFalse($r2['ok']); $this->assertEquals(409,$r2['http']);
    }
    public function testIdempotencyReplayReturnsStored(): void
    {
        $body='{"operation":"hosting.create_identity","payload":{"account_id":5}}';
        $idem='idem-fixed-12345';
        $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body,$idem);
        $r=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h);
        $this->assertTrue($r['ok']);
        // Simulate storing completion
        $store=new NodeIdempotencyStore($this->db);
        $store->reserve($this->nodeId,$idem,'hosting.create_identity',$h[NodeApiSigner::HEADER_REQUEST_ID]);
        $store->complete($this->nodeId,$idem,200,['status'=>'ok','operation'=>'hosting.create_identity']);
        // Second request with same idempotency key but different nonce/request_id
        $h2=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body,$idem);
        $r2=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h2);
        $this->assertTrue($r2['ok']); $this->assertTrue($r2['idempotent_replay']);
    }
    public function testDisabledNodeRejected(): void
    {
        $this->db->execute("UPDATE hosting_nodes SET status='offline' WHERE id=?",[$this->nodeId]);
        $body='{}'; $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$body);
        $r=$this->auth('POST','/v1/node/hosting/create-identity',$body,$h);
        $this->assertFalse($r['ok']); $this->assertEquals(403,$r['http']);
        $this->db->execute("UPDATE hosting_nodes SET status='active' WHERE id=?",[$this->nodeId]);
    }
    public function testPayloadValidation(): void
    {
        $errors=NodeRequestValidator::validatePayload('hosting.create_identity',['account_id'=>0]);
        $this->assertNotEmpty($errors);
        $errors2=NodeRequestValidator::validatePayload('hosting.create_identity',['account_id'=>1,'username'=>'evil']);
        $this->assertNotEmpty($errors2);
        $ok=NodeRequestValidator::validatePayload('hosting.create_identity',['account_id'=>1]);
        $this->assertEmpty($ok);
    }
    public function testRequestSizeLimit(): void
    {
        $big=str_repeat('a',70000);
        $h=$this->validHeaders('POST','/v1/node/hosting/create-identity',$big);
        $r=$this->auth('POST','/v1/node/hosting/create-identity',$big,$h);
        $this->assertFalse($r['ok']);
    }
}
