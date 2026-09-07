<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Helpers\Database;
use App\Models\HostingAccount;
use App\Services\NodeApi\NodeApiClient;

/**
 * NodeApiProvisioner — delegates typed operations to the authenticated Node API (P3).
 * Falls back to LocalMock for nodes without API URL (development).
 */
final class NodeApiProvisioner implements HostingProvisionerInterface
{
    public function __construct(
        private readonly Database $db,
        private readonly NodeApiClient $client,
        private readonly HostingProvisionerInterface $fallback
    ) {}

    private function dispatch(HostingAccount $account, string $op, array $payload): ProvisionResult
    {
        $nodeId = null;
        // Try to resolve node from account's last provisioning job or hosting_nodes
        // For P3, we use the first active node's URL if account has no explicit node binding
        // Simplified: use account->id to pick node via hosting_accounts.node_id if exists
        $row = $this->db->fetch("SELECT node_id FROM provisioning_jobs WHERE hosting_account_id=? ORDER BY id DESC LIMIT 1", [$account->id]);
        $nodeId = $row['node_id'] ?? null;
        if ($nodeId === null) {
            // fallback: first active node
            $n = $this->db->fetch("SELECT id FROM hosting_nodes WHERE status='active' ORDER BY id ASC LIMIT 1");
            $nodeId = $n['id'] ?? null;
        }
        if ($nodeId === null) {
            return $this->fallback->createHostingAccount($account);
        }
        $node = $this->db->fetch("SELECT api_url FROM hosting_nodes WHERE id=?", [$nodeId]);
        if (!$node || empty($node['api_url'])) {
            // No node API configured -> local mock
            return $this->fallbackFallback($op, $account, $payload);
        }
        $opMap = [
            'createHostingAccount' => ['path'=>'/v1/node/hosting/create-identity','op'=>'hosting.create_identity'],
            'suspendHostingAccount' => ['path'=>'/v1/node/hosting/suspend','op'=>'hosting.suspend'],
            'activateHostingAccount' => ['path'=>'/v1/node/hosting/unsuspend','op'=>'hosting.unsuspend'],
            'terminateHostingAccount' => ['path'=>'/v1/node/hosting/terminate','op'=>'hosting.terminate'],
            'createSubdomain' => ['path'=>'/v1/node/hosting/create-filesystem','op'=>'hosting.create_filesystem'],
            'deleteSubdomain' => ['path'=>'/v1/node/hosting/create-filesystem','op'=>'hosting.create_filesystem'],
            'createDatabase' => ['path'=>'/v1/node/hosting/create-identity','op'=>'hosting.create_identity'],
            'deleteDatabase' => ['path'=>'/v1/node/hosting/terminate','op'=>'hosting.terminate'],
            'createDatabaseUser' => ['path'=>'/v1/node/hosting/create-identity','op'=>'hosting.create_identity'],
            'deleteDatabaseUser' => ['path'=>'/v1/node/hosting/terminate','op'=>'hosting.terminate'],
        ];
        $target = $opMap[$op] ?? null;
        if ($target === null) {
            return ProvisionResult::fail('Unknown operation');
        }
        $payload['account_id'] = $account->id;
        $body = ['operation'=>$target['op'],'payload'=>$payload];
        $idempotency = 'node-'.$account->id.'-'.$op.'-'.hash('sha256', json_encode($payload));
        try {
            $resp = $this->client->request((int)$nodeId, 'POST', $target['path'], $body, $idempotency);
            if ($resp['http'] >= 200 && $resp['http'] < 300) {
                return ProvisionResult::ok('Node API '.$op.' ok', $resp['body']);
            }
            if ($resp['http'] === 409 || $resp['http'] === 429) {
                // Retryable conflict/rate limit
                return ProvisionResult::fail('Node API retryable: '.$resp['http']);
            }
            return ProvisionResult::fail('Node API error: '.($resp['body']['error']['code'] ?? $resp['http']));
        } catch (\Throwable $e) {
            // Fallback to local on connection failure in dev
            if (str_contains($e->getMessage(), 'Node URL')) {
                return $this->fallbackFallback($op, $account, $payload);
            }
            return ProvisionResult::fail('Node API exception: '.$e->getMessage());
        }
    }

    private function fallbackFallback(string $op, HostingAccount $account, array $payload): ProvisionResult
    {
        return match($op) {
            'createHostingAccount' => $this->fallback->createHostingAccount($account),
            'suspendHostingAccount' => $this->fallback->suspendHostingAccount($account),
            'activateHostingAccount' => $this->fallback->activateHostingAccount($account),
            'terminateHostingAccount' => $this->fallback->terminateHostingAccount($account),
            'createSubdomain' => $this->fallback->createSubdomain($account, $payload['subdomain'] ?? '', $payload['fullDomain'] ?? ''),
            'deleteSubdomain' => $this->fallback->deleteSubdomain($account, $payload['fullDomain'] ?? ''),
            'createDatabase' => $this->fallback->createDatabase($account, $payload['dbName'] ?? ''),
            'deleteDatabase' => $this->fallback->deleteDatabase($account, $payload['dbName'] ?? ''),
            'createDatabaseUser' => $this->fallback->createDatabaseUser($account, $payload['username'] ?? '', $payload['password'] ?? ''),
            'deleteDatabaseUser' => $this->fallback->deleteDatabaseUser($account, $payload['username'] ?? ''),
            default => ProvisionResult::fail('Unknown operation'),
        };
    }

    public function createHostingAccount(HostingAccount $account): ProvisionResult { return $this->dispatch($account,'createHostingAccount',[]); }
    public function suspendHostingAccount(HostingAccount $account): ProvisionResult { return $this->dispatch($account,'suspendHostingAccount',[]); }
    public function activateHostingAccount(HostingAccount $account): ProvisionResult { return $this->dispatch($account,'activateHostingAccount',[]); }
    public function terminateHostingAccount(HostingAccount $account): ProvisionResult { return $this->dispatch($account,'terminateHostingAccount',[]); }
    public function createSubdomain(HostingAccount $account, string $subdomain, string $fullDomain): ProvisionResult { return $this->dispatch($account,'createSubdomain',['subdomain'=>$subdomain,'fullDomain'=>$fullDomain]); }
    public function deleteSubdomain(HostingAccount $account, string $fullDomain): ProvisionResult { return $this->dispatch($account,'deleteSubdomain',['fullDomain'=>$fullDomain]); }
    public function createDatabase(HostingAccount $account, string $dbName): ProvisionResult { return $this->dispatch($account,'createDatabase',['dbName'=>$dbName]); }
    public function deleteDatabase(HostingAccount $account, string $dbName): ProvisionResult { return $this->dispatch($account,'deleteDatabase',['dbName'=>$dbName]); }
    public function createDatabaseUser(HostingAccount $account, string $dbUsername, string $password): ProvisionResult { return $this->dispatch($account,'createDatabaseUser',['username'=>$dbUsername,'password'=>$password]); }
    public function deleteDatabaseUser(HostingAccount $account, string $dbUsername): ProvisionResult { return $this->dispatch($account,'deleteDatabaseUser',['username'=>$dbUsername]); }
}
