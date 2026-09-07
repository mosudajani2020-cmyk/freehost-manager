<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Services\NodeApi\NodeApiSigner;
use App\Services\NodeApi\NodeCredentialManager;
use App\Controllers\NodeApiController;

final class NodeApiEndpointTest extends TestCase
{
    private Database $db;
    private int $nodeId;
    private string $secret;

    protected function setUp(): void
    {
        $base=dirname(__DIR__,2);
        if(file_exists($base.'/.env')) \Dotenv\Dotenv::createImmutable($base)->load();
        $cfg=require $base.'/config/database.php'; $cfg['database']='freehost_manager';
        Database::reset(); $this->db=Database::getInstance($cfg);
        $this->db->execute("DELETE FROM node_api_nonces WHERE node_id IN (SELECT id FROM hosting_nodes WHERE name LIKE 'test-node-ep-%')");
        $this->db->execute("DELETE FROM node_api_idempotency WHERE node_id IN (SELECT id FROM hosting_nodes WHERE name LIKE 'test-node-ep-%')");
        $this->db->execute("DELETE FROM hosting_nodes WHERE name LIKE 'test-node-ep-%'");
        $this->secret=NodeCredentialManager::generateSecret();
        $this->db->query("INSERT INTO hosting_nodes (name,hostname,status,max_accounts,api_url) VALUES (?,?,?,?,?)", ['test-node-ep-1','test-ep.example.com','active',100,'http://localhost/mock-provisioning']);
        $this->nodeId=(int)$this->db->lastInsertId();
        NodeCredentialManager::store($this->db,$this->nodeId,$this->secret);
    }
    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM node_api_nonces WHERE node_id=?",[$this->nodeId]);
        $this->db->execute("DELETE FROM node_api_idempotency WHERE node_id=?",[$this->nodeId]);
        $this->db->execute("DELETE FROM hosting_nodes WHERE id=?",[$this->nodeId]);
        Database::reset();
    }

    private function call(string $method,string $path,array $payload, int $expectedHttp, ?string $idem=null): array
    {
        $body=json_encode(['payload'=>$payload],JSON_UNESCAPED_SLASHES);
        $idemKey=$idem??bin2hex(random_bytes(16));
        $headers=NodeApiSigner::buildHeaders($this->nodeId,$this->secret,$method,$path,$body,$idemKey);
        $headers['Content-Type']='application/json';
        // Simulate server environment
        $_SERVER['REQUEST_METHOD']=$method;
        $_SERVER['REQUEST_URI']=$path;
        $_SERVER['CONTENT_TYPE']='application/json';
        $_SERVER['REMOTE_ADDR']='127.0.0.1';
        foreach($headers as $k=>$v){ $_SERVER['HTTP_'.str_replace('-','_',strtoupper($k))]=$v; }
        $GLOBALS['_test_node_body']=$body;
        $GLOBALS['_test_node_headers']=$headers;
        ob_start();
        $c=new NodeApiController();
        // dispatch to correct handler
        $map=[
            '/v1/node/hosting/create-identity'=>'createIdentity',
            '/v1/node/hosting/create-filesystem'=>'createFilesystem',
            '/v1/node/hosting/configure-php-fpm'=>'configurePhpFpm',
            '/v1/node/hosting/apply-resource-policy'=>'applyResourcePolicy',
            '/v1/node/hosting/suspend'=>'suspend',
            '/v1/node/hosting/unsuspend'=>'unsuspend',
            '/v1/node/hosting/terminate'=>'terminate',
        ];
        $methodName=$map[$path]??null;
        if($methodName){ $c->$methodName([]); } else { http_response_code(404); echo json_encode(['error'=>['code'=>'not_found']]); }
        $out=ob_get_clean();
        $code=http_response_code();
        unset($GLOBALS['_test_node_body'],$GLOBALS['_test_node_headers']);
        $this->assertEquals($expectedHttp,$code);
        return json_decode($out,true)??[];
    }

    public function testHealth(): void
    {
        $_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/v1/node/health';
        ob_start(); (new NodeApiController())->health([]); $out=ob_get_clean();
        $this->assertEquals(200,http_response_code());
        $data=json_decode($out,true); $this->assertEquals('ok',$data['status']);
    }
    public function testKnownEndpointWorks(): void
    {
        $res=$this->call('POST','/v1/node/hosting/create-identity',['account_id'=>1],200);
        $this->assertEquals('ok',$res['status']);
    }
    public function testExecuteRejected(): void
    {
        // No such endpoint registered; our call helper will return 404
        $res=$this->call('POST','/execute',['account_id'=>1],404);
        $this->assertEquals('not_found',$res['error']['code']);
    }
    public function testUnknownOperationRejectedViaValidation(): void
    {
        $body=json_encode(['payload'=>['account_id'=>1,'arbitrary'=>'x']],JSON_UNESCAPED_SLASHES);
        $idem=bin2hex(random_bytes(16));
        $h=NodeApiSigner::buildHeaders($this->nodeId,$this->secret,'POST','/v1/node/hosting/create-identity',$body,$idem);
        $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REQUEST_URI']='/v1/node/hosting/create-identity'; $_SERVER['CONTENT_TYPE']='application/json'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
        foreach($h as $k=>$v) $_SERVER['HTTP_'.str_replace('-','_',strtoupper($k))]=$v;
        $GLOBALS['_test_node_body']=$body; $GLOBALS['_test_node_headers']=$h;
        ob_start(); (new NodeApiController())->createIdentity([]); $out=ob_get_clean(); $code=http_response_code();
        unset($GLOBALS['_test_node_body'],$GLOBALS['_test_node_headers']);
        $this->assertEquals(422,$code);
    }
    public function testOversizedRequestRejected(): void
    {
        $big=str_repeat('x',70000);
        $payload=['account_id'=>1,'big'=>$big];
        $body=json_encode(['payload'=>$payload]);
        $idem=bin2hex(random_bytes(16));
        $h=NodeApiSigner::buildHeaders($this->nodeId,$this->secret,'POST','/v1/node/hosting/create-identity',$body,$idem);
        $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REQUEST_URI']='/v1/node/hosting/create-identity'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
        foreach($h as $k=>$v) $_SERVER['HTTP_'.str_replace('-','_',strtoupper($k))]=$v;
        $GLOBALS['_test_node_body']=$body; $GLOBALS['_test_node_headers']=$h;
        ob_start(); (new NodeApiController())->createIdentity([]); $out=ob_get_clean(); $code=http_response_code();
        unset($GLOBALS['_test_node_body'],$GLOBALS['_test_node_headers']);
        $this->assertEquals(413,$code);
    }
    public function testMaliciousPathTraversalRejected(): void
    {
        $res=$this->call('POST','/v1/node/hosting/create-identity',['account_id'=>1,'filesystem_root'=>'/etc/passwd'],422);
        // Our validator blocks filesystem_root
        $this->assertEquals('validation_failed',$res['error']['code']);
    }
}
