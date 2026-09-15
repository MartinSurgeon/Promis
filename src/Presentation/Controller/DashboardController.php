<?php

declare(strict_types=1);

namespace Promis\Src\Presentation\Controller;

use PDO;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Support\View;

/**
 * Institutional Role-Based Operational Dashboard Controller.
 * Delivers role-aware operational queues, approval pipelines, governance KPIs,
 * budget ceilings, and administrative telemetry with strict server-side authorization.
 */
final class DashboardController
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Render the role-based operational dashboard.
     */
    public function index(Request $request): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $roles = $user['roles'] ?? [];

        $isRequester = in_array('REQUESTER', $roles, true);
        $isHod = in_array('HOD', $roles, true);
        $isDean = in_array('DEAN', $roles, true);
        $isFinance = in_array('FINANCE_OFFICER', $roles, true);
        $isProcurement = in_array('PROCUREMENT_OFFICER', $roles, true);
        $isAdmin = in_array('ADMIN', $roles, true) || in_array('SUPER_ADMIN', $roles, true);

        // If no explicit operational role is recognized, default to Requester role view
        if (empty($roles)) {
            $isRequester = true;
        }

        // 1. Resolve authorized entity scopes for HOD & Dean roles
        $hodEntityIds = $isHod ? $this->resolveHodEntityIds($userId) : [];
        $deanEntityIds = $isDean ? $this->resolveDeanEntityIds($userId) : [];

        // 2. Resolve Role-Specific KPIs
        $kpis = $this->resolveRoleKpis($userId, $roles, $hodEntityIds, $deanEntityIds);

        // 3. Resolve Role-Specific Approval Queues / Datasets
        $requesterQueues = $isRequester ? $this->resolveRequesterQueues($userId) : [];
        $hodQueue = $isHod ? $this->resolveHodQueue($hodEntityIds) : [];
        $deanQueue = $isDean ? $this->resolveDeanQueue($deanEntityIds) : [];
        $financeQueue = $isFinance ? $this->resolveFinanceQueue() : [];
        $procurementQueue = $isProcurement ? $this->resolveProcurementQueue() : [];

        // 4. Admin Telemetry & Statistics
        $adminStats = $isAdmin ? $this->resolveAdminStats() : [];
        $recentAuditLogs = $isAdmin ? $this->resolveRecentAuditLogs() : [];

        // 5. Budget Summary (Institutional Overview)
        $budgetSummary = $this->resolveBudgetSummary();

        // 6. Recent Requisitions (General activity)
        $recentRequisitions = $this->resolveRecentRequisitions($userId, $roles, $hodEntityIds, $deanEntityIds);

        // 7. Active Queue Tab Determination
        $activeTab = trim((string)$request->query('tab', ''));
        if ($activeTab === '') {
            $activeTab = match(true) {
                $isHod => 'hod',
                $isDean => 'dean',
                $isFinance => 'finance',
                $isProcurement => 'procurement',
                $isAdmin => 'admin',
                default => 'requester',
            };
        }

        $html = View::render('dashboard/index', [
            'title' => 'PROMIS - Operational Workflow Dashboard',
            'user' => $user,
            'roles' => $roles,
            'isRequester' => $isRequester,
            'isHod' => $isHod,
            'isDean' => $isDean,
            'isFinance' => $isFinance,
            'isProcurement' => $isProcurement,
            'isAdmin' => $isAdmin,
            'kpis' => $kpis,
            'requesterQueues' => $requesterQueues,
            'hodQueue' => $hodQueue,
            'deanQueue' => $deanQueue,
            'financeQueue' => $financeQueue,
            'procurementQueue' => $procurementQueue,
            'adminStats' => $adminStats,
            'recentAuditLogs' => $recentAuditLogs,
            'budgetSummary' => $budgetSummary,
            'recentRequisitions' => $recentRequisitions,
            'activeTab' => $activeTab,
            'appUrl' => $this->resolveAppUrl($request),
            'activeNav' => 'dashboard',
            'pageTitle' => 'Operational Dashboard & Approval Queues',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => ''],
            ],
        ], 'app');

        return Response::html($html);
    }

    /**
     * Resolve HOD authorized department planning entity IDs.
     */
    public function resolveHodEntityIds(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT uer.planning_entity_id 
                FROM user_entity_roles uer 
                JOIN roles r ON r.id = uer.role_id 
                WHERE uer.user_id = :uid 
                  AND r.role_code = 'HOD' 
                  AND uer.status = 'ACTIVE'
            ");
            $stmt->execute([':uid' => $userId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve Dean authorized faculty and child department planning entity IDs via closure table.
     */
    public function resolveDeanEntityIds(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT eh.descendant_entity_id 
                FROM entity_hierarchies eh 
                JOIN user_entity_roles uer ON uer.planning_entity_id = eh.ancestor_entity_id 
                JOIN roles r ON r.id = uer.role_id 
                WHERE uer.user_id = :uid 
                  AND r.role_code = 'DEAN' 
                  AND uer.status = 'ACTIVE'
                UNION
                SELECT DISTINCT uer.planning_entity_id
                FROM user_entity_roles uer
                JOIN roles r ON r.id = uer.role_id
                WHERE uer.user_id = :uid2
                  AND r.role_code = 'DEAN'
                  AND uer.status = 'ACTIVE'
            ");
            $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Compute Role-Specific KPI Metric Counters.
     */
    public function resolveRoleKpis(int $userId, array $roles, array $hodEntityIds = [], array $deanEntityIds = []): array
    {
        $kpis = [
            'requester' => [
                'drafts' => 0,
                'submitted' => 0,
                'returned' => 0,
                'rejected' => 0,
                'completed' => 0,
            ],
            'hod' => [
                'awaiting' => 0,
                'endorsed' => 0,
                'returned' => 0,
                'rejected' => 0,
            ],
            'dean' => [
                'awaiting' => 0,
                'approved' => 0,
                'returned' => 0,
                'rejected' => 0,
            ],
            'finance' => [
                'awaiting' => 0,
                'committed' => 0,
                'returned' => 0,
                'rejected' => 0,
            ],
            'procurement' => [
                'awaiting' => 0,
                'processing' => 0,
                'received' => 0,
                'completed' => 0,
            ],
            'admin' => [
                'total_users' => 0,
                'active_users' => 0,
                'planning_entities' => 0,
                'active_workflows' => 0,
            ],
        ];

        try {
            // 1. Requester KPIs
            $rStmt = $this->db->prepare("
                SELECT status, COUNT(*) as count 
                FROM requisitions 
                WHERE created_by = :uid 
                GROUP BY status
            ");
            $rStmt->execute([':uid' => $userId]);
            $rRows = $rStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rRows as $row) {
                $st = strtoupper((string)$row['status']);
                $cnt = (int)$row['count'];
                match ($st) {
                    'DRAFT' => $kpis['requester']['drafts'] += $cnt,
                    'SUBMITTED', 'ENDORSED', 'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED' => $kpis['requester']['submitted'] += $cnt,
                    'RETURNED' => $kpis['requester']['returned'] += $cnt,
                    'REJECTED' => $kpis['requester']['rejected'] += $cnt,
                    'PROCUREMENT_RECEIVED' => $kpis['requester']['completed'] += $cnt,
                    default => null,
                };
            }

            // 2. HOD KPIs (Scoped to HOD Department Entities)
            if (!empty($hodEntityIds)) {
                $inSql = implode(',', array_map('intval', $hodEntityIds));
                $hStmt = $this->db->query("
                    SELECT status, COUNT(*) as count 
                    FROM requisitions 
                    WHERE planning_entity_id IN ({$inSql}) 
                    GROUP BY status
                ");
                $hRows = $hStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($hRows as $row) {
                    $st = strtoupper((string)$row['status']);
                    $cnt = (int)$row['count'];
                    match ($st) {
                        'SUBMITTED' => $kpis['hod']['awaiting'] += $cnt,
                        'ENDORSED', 'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED', 'PROCUREMENT_RECEIVED' => $kpis['hod']['endorsed'] += $cnt,
                        'RETURNED' => $kpis['hod']['returned'] += $cnt,
                        'REJECTED' => $kpis['hod']['rejected'] += $cnt,
                        default => null,
                    };
                }
            }

            // 3. Dean KPIs (Scoped to Dean Faculty Tree Entities)
            if (!empty($deanEntityIds)) {
                $inDeanSql = implode(',', array_map('intval', $deanEntityIds));
                $dStmt = $this->db->query("
                    SELECT status, COUNT(*) as count 
                    FROM requisitions 
                    WHERE planning_entity_id IN ({$inDeanSql}) 
                    GROUP BY status
                ");
                $dRows = $dStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($dRows as $row) {
                    $st = strtoupper((string)$row['status']);
                    $cnt = (int)$row['count'];
                    match ($st) {
                        'ENDORSED' => $kpis['dean']['awaiting'] += $cnt,
                        'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED', 'PROCUREMENT_RECEIVED' => $kpis['dean']['approved'] += $cnt,
                        'RETURNED' => $kpis['dean']['returned'] += $cnt,
                        'REJECTED' => $kpis['dean']['rejected'] += $cnt,
                        default => null,
                    };
                }
            }

            // 4. Finance KPIs (Institutional Scope)
            $fStmt = $this->db->query("
                SELECT status, COUNT(*) as count 
                FROM requisitions 
                GROUP BY status
            ");
            $fRows = $fStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fRows as $row) {
                $st = strtoupper((string)$row['status']);
                $cnt = (int)$row['count'];
                match ($st) {
                    'DEPARTMENT_APPROVED' => $kpis['finance']['awaiting'] += $cnt,
                    'COMMITMENT_AUTHORIZED', 'PROCUREMENT_RECEIVED' => $kpis['finance']['committed'] += $cnt,
                    'RETURNED' => $kpis['finance']['returned'] += $cnt,
                    'REJECTED' => $kpis['finance']['rejected'] += $cnt,
                    default => null,
                };
            }

            // 5. Procurement KPIs (Institutional Scope)
            foreach ($fRows as $row) {
                $st = strtoupper((string)$row['status']);
                $cnt = (int)$row['count'];
                if ($st === 'COMMITMENT_AUTHORIZED') {
                    $kpis['procurement']['awaiting'] += $cnt;
                    $kpis['procurement']['processing'] += $cnt;
                } elseif ($st === 'PROCUREMENT_RECEIVED') {
                    $kpis['procurement']['received'] += $cnt;
                    $kpis['procurement']['completed'] += $cnt;
                }
            }

            // 6. Admin Statistics
            $kpis['admin']['total_users'] = (int)$this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $kpis['admin']['active_users'] = (int)$this->db->query("SELECT COUNT(*) FROM users WHERE status = 'ACTIVE'")->fetchColumn();
            $kpis['admin']['planning_entities'] = (int)$this->db->query("SELECT COUNT(*) FROM planning_entities WHERE is_active = 1")->fetchColumn();
            $kpis['admin']['active_workflows'] = (int)$this->db->query("SELECT COUNT(*) FROM requisitions WHERE status NOT IN ('DRAFT', 'PROCUREMENT_RECEIVED', 'REJECTED')")->fetchColumn();

        } catch (\Throwable) {
            // Non-breaking fallback
        }

        return $kpis;
    }

    /**
     * Resolve Requester Worklists (Drafts, Submitted, Returned, Rejected, Completed, Recent).
     */
    public function resolveRequesterQueues(int $userId): array
    {
        return [
            'drafts' => $this->fetchRequisitionRows("r.created_by = :uid AND r.status = 'DRAFT'", [':uid' => $userId], 15),
            'submitted' => $this->fetchRequisitionRows("r.created_by = :uid AND r.status IN ('SUBMITTED', 'ENDORSED', 'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED')", [':uid' => $userId], 15),
            'returned' => $this->fetchRequisitionRows("r.created_by = :uid AND r.status = 'RETURNED'", [':uid' => $userId], 15),
            'rejected' => $this->fetchRequisitionRows("r.created_by = :uid AND r.status = 'REJECTED'", [':uid' => $userId], 15),
            'completed' => $this->fetchRequisitionRows("r.created_by = :uid AND r.status = 'PROCUREMENT_RECEIVED'", [':uid' => $userId], 15),
            'recent' => $this->fetchRequisitionRows("r.created_by = :uid", [':uid' => $userId], 10),
        ];
    }

    /**
     * Resolve HOD Department Approval Queue (Awaiting Endorsement, Endorsed, Returned, Rejected).
     */
    public function resolveHodQueue(array $hodEntityIds): array
    {
        if (empty($hodEntityIds)) {
            return ['awaiting' => [], 'endorsed' => [], 'returned' => [], 'rejected' => []];
        }

        $inSql = implode(',', array_map('intval', $hodEntityIds));

        return [
            'awaiting' => $this->fetchRequisitionRows("r.planning_entity_id IN ({$inSql}) AND r.status = 'SUBMITTED'", [], 20),
            'endorsed' => $this->fetchRequisitionRows("r.planning_entity_id IN ({$inSql}) AND r.status IN ('ENDORSED', 'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED', 'PROCUREMENT_RECEIVED')", [], 15),
            'returned' => $this->fetchRequisitionRows("r.planning_entity_id IN ({$inSql}) AND r.status = 'RETURNED'", [], 15),
            'rejected' => $this->fetchRequisitionRows("r.planning_entity_id IN ({$inSql}) AND r.status = 'REJECTED'", [], 15),
        ];
    }

    /**
     * Resolve Dean Faculty Approval Queue (Awaiting Approval, Approved, Returned, Rejected).
     */
    public function resolveDeanQueue(array $deanEntityIds): array
    {
        if (empty($deanEntityIds)) {
            return ['awaiting' => [], 'approved' => [], 'returned' => [], 'rejected' => []];
        }

        $inSql = implode(',', array_map('intval', $deanEntityIds));

        return [
            'awaiting' => $this->fetchRequisitionRows("r.planning_entity_id IN ({$inSql}) AND r.status = 'ENDORSED'", [], 20),
            'approved' => $this->fetchRequisitionRows("r.planning_entity_id IN ({$inSql}) AND r.status IN ('DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED', 'PROCUREMENT_RECEIVED')", [], 15),
            'returned' => $this->fetchRequisitionRows("r.planning_entity_id IN ({$inSql}) AND r.status = 'RETURNED'", [], 15),
            'rejected' => $this->fetchRequisitionRows("r.planning_entity_id IN ({$inSql}) AND r.status = 'REJECTED'", [], 15),
        ];
    }

    /**
     * Resolve Finance Commitment Queue (Awaiting Commitment, Committed, Returned, Rejected).
     */
    public function resolveFinanceQueue(): array
    {
        return [
            'awaiting' => $this->fetchRequisitionRows("r.status = 'DEPARTMENT_APPROVED'", [], 20),
            'committed' => $this->fetchRequisitionRows("r.status IN ('COMMITMENT_AUTHORIZED', 'PROCUREMENT_RECEIVED')", [], 15),
            'returned' => $this->fetchRequisitionRows("r.status = 'RETURNED'", [], 15),
            'rejected' => $this->fetchRequisitionRows("r.status = 'REJECTED'", [], 15),
        ];
    }

    /**
     * Resolve Procurement Queue (Awaiting Processing, Received/Completed).
     */
    public function resolveProcurementQueue(): array
    {
        return [
            'awaiting' => $this->fetchRequisitionRows("r.status = 'COMMITMENT_AUTHORIZED'", [], 20),
            'received' => $this->fetchRequisitionRows("r.status = 'PROCUREMENT_RECEIVED'", [], 15),
        ];
    }

    /**
     * Resolve Admin Statistics telemetry.
     */
    public function resolveAdminStats(): array
    {
        try {
            return [
                'total_users' => (int)$this->db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                'active_users' => (int)$this->db->query("SELECT COUNT(*) FROM users WHERE status = 'ACTIVE'")->fetchColumn(),
                'pending_users' => (int)$this->db->query("SELECT COUNT(*) FROM users WHERE status = 'PENDING'")->fetchColumn(),
                'total_entities' => (int)$this->db->query("SELECT COUNT(*) FROM planning_entities")->fetchColumn(),
                'planning_entities' => (int)$this->db->query("SELECT COUNT(*) FROM planning_entities WHERE is_active = 1")->fetchColumn(),
                'active_entities' => (int)$this->db->query("SELECT COUNT(*) FROM planning_entities WHERE is_active = 1")->fetchColumn(),
                'active_workflows' => (int)$this->db->query("SELECT COUNT(*) FROM requisitions WHERE status NOT IN ('DRAFT', 'PROCUREMENT_RECEIVED', 'REJECTED')")->fetchColumn(),
                'total_requisitions' => (int)$this->db->query("SELECT COUNT(*) FROM requisitions")->fetchColumn(),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Base Helper to Query Hydrated Requisition Rows with Department, Faculty and Requester metadata.
     */
    private function fetchRequisitionRows(string $whereSql, array $params = [], int $limit = 15): array
    {
        try {
            $sql = "
                SELECT r.id, r.requisition_number, r.planning_entity_id, r.fiscal_year, r.approved_plan_version_id,
                       r.status, r.total_estimated_cost, r.justification, r.submitted_at, r.submitted_by,
                       r.created_at, r.created_by, r.updated_at,
                       pe.entity_name as department_name, pe.entity_code as department_code,
                       parent_pe.entity_name as faculty_name, parent_pe.entity_code as faculty_code,
                       CONCAT(u.first_name, ' ', u.last_name) as requester_name, u.email as requester_email
                FROM requisitions r
                JOIN planning_entities pe ON pe.id = r.planning_entity_id
                LEFT JOIN planning_entities parent_pe ON parent_pe.id = pe.parent_entity_id
                JOIN users u ON u.id = r.created_by
                WHERE {$whereSql}
                ORDER BY r.created_at DESC
                LIMIT {$limit}
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve recent requisitions for general dashboard activity.
     */
    private function resolveRecentRequisitions(int $userId, array $roles, array $hodEntityIds = [], array $deanEntityIds = []): array
    {
        try {
            $where = '1=1';
            $params = [];

            if (in_array('REQUESTER', $roles, true) && !in_array('ADMIN', $roles, true) && !in_array('SUPER_ADMIN', $roles, true) && !in_array('DEAN', $roles, true) && !in_array('FINANCE_OFFICER', $roles, true) && !in_array('PROCUREMENT_OFFICER', $roles, true)) {
                if (in_array('HOD', $roles, true) && !empty($hodEntityIds)) {
                    $inHod = implode(',', array_map('intval', $hodEntityIds));
                    $where = "(r.created_by = :uid OR r.planning_entity_id IN ({$inHod}))";
                    $params[':uid'] = $userId;
                } else {
                    $where = "r.created_by = :uid";
                    $params[':uid'] = $userId;
                }
            }

            return $this->fetchRequisitionRows($where, $params, 10);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve Budget Overview (Allocated, Committed, Available Balance).
     */
    private function resolveBudgetSummary(): array
    {
        $summary = [
            'total_allocated' => '0.00',
            'total_committed' => '0.00',
            'available_balance' => '0.00',
            'fiscal_year' => (int)date('Y'),
            'has_live_data' => false,
        ];

        try {
            // Query allocated budget for current year
            $stmt = $this->db->query("
                SELECT COALESCE(SUM(allocated_amount), 0.00) as allocated 
                FROM budget_allocations 
                WHERE is_active = 1
            ");
            $allocatedRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $allocated = (string)($allocatedRow['allocated'] ?? '0.00');

            // Query committed amounts
            $cStmt = $this->db->query("
                SELECT COALESCE(SUM(authorized_amount), 0.00) as committed 
                FROM commitment_authorizations 
                WHERE authorization_status = 'AUTHORIZED'
            ");
            $committedRow = $cStmt->fetch(PDO::FETCH_ASSOC);
            $committed = (string)($committedRow['committed'] ?? '0.00');

            if (bccomp($allocated, '0.00', 2) > 0 || bccomp($committed, '0.00', 2) > 0) {
                $summary['total_allocated'] = $allocated;
                $summary['total_committed'] = $committed;
                $summary['available_balance'] = bcsub($allocated, $committed, 2);
                $summary['has_live_data'] = true;
            }
        } catch (\Throwable) {
            // Graceful fallback
        }

        return $summary;
    }

    /**
     * Resolve recent audit logs for administrators.
     */
    private function resolveRecentAuditLogs(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT a.id, a.event_timestamp, a.action, a.record_type, a.record_id, 
                       a.ip_address, u.username, CONCAT(u.first_name, ' ', u.last_name) as full_name
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.actor_user_id
                ORDER BY a.event_timestamp DESC
                LIMIT 8
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveAppUrl(Request $request): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = dirname($scriptName);
        if ($base === '/' || $base === '\\') {
            return '';
        }
        return rtrim($base, '/\\');
    }
}
