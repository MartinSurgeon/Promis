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
 * Institutional Dashboard Controller.
 * Delivers role-aware governance metrics, approval queues, budget availability overviews,
 * and recent requisition activities.
 */
final class DashboardController
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Render the role-aware dashboard.
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

        // Fetch Real Database Counts
        $metrics = $this->resolveMetrics($userId, $roles);
        $recentRequisitions = $this->resolveRecentRequisitions($userId, $roles);
        $budgetSummary = $this->resolveBudgetSummary();
        $recentAuditLogs = $isAdmin ? $this->resolveRecentAuditLogs() : [];

        $html = View::render('dashboard/index', [
            'title' => 'PROMIS - Institutional Dashboard',
            'user' => $user,
            'roles' => $roles,
            'isRequester' => $isRequester,
            'isHod' => $isHod,
            'isDean' => $isDean,
            'isFinance' => $isFinance,
            'isProcurement' => $isProcurement,
            'isAdmin' => $isAdmin,
            'metrics' => $metrics,
            'recentRequisitions' => $recentRequisitions,
            'budgetSummary' => $budgetSummary,
            'recentAuditLogs' => $recentAuditLogs,
            'appUrl' => $this->resolveAppUrl($request),
            'activeNav' => 'dashboard',
            'pageTitle' => 'Institutional Dashboard',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => ''],
            ],
        ], 'app');

        return Response::html($html);
    }

    /**
     * Query real live metrics based on user roles.
     */
    private function resolveMetrics(int $userId, array $roles): array
    {
        $metrics = [
            'drafts' => 0,
            'submitted' => 0,
            'returned' => 0,
            'pending_endorsement' => 0,
            'pending_approval' => 0,
            'pending_commitment' => 0,
            'awaiting_receipt' => 0,
            'completed' => 0,
            'total_requisitions' => 0,
        ];

        try {
            // Requisition counts by status
            $stmt = $this->db->query("
                SELECT status, COUNT(*) as count 
                FROM requisitions 
                GROUP BY status
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $status = strtoupper((string)$row['status']);
                $cnt = (int)$row['count'];
                $metrics['total_requisitions'] += $cnt;

                match ($status) {
                    'DRAFT' => $metrics['drafts'] = $cnt,
                    'SUBMITTED' => $metrics['pending_endorsement'] = $cnt,
                    'ENDORSED' => $metrics['pending_approval'] = $cnt,
                    'DEPARTMENT_APPROVED', 'APPROVED' => $metrics['pending_commitment'] = $cnt,
                    'COMMITMENT_AUTHORIZED', 'COMMITTED' => $metrics['awaiting_receipt'] = $cnt,
                    'PROCUREMENT_RECEIVED', 'RECEIVED' => $metrics['completed'] = $cnt,
                    'RETURNED' => $metrics['returned'] = $cnt,
                    'REJECTED' => $metrics['rejected'] = $cnt,
                    default => null,
                };
            }

            // User-specific draft & returned counts if requester
            if (in_array('REQUESTER', $roles, true)) {
                $uStmt = $this->db->prepare("
                    SELECT status, COUNT(*) as count 
                    FROM requisitions 
                    WHERE created_by = :uid 
                    GROUP BY status
                ");
                $uStmt->execute([':uid' => $userId]);
                $uRows = $uStmt->fetchAll(PDO::FETCH_ASSOC);
                $metrics['my_drafts'] = 0;
                $metrics['my_submitted'] = 0;
                $metrics['my_returned'] = 0;
                foreach ($uRows as $ur) {
                    $st = strtoupper((string)$ur['status']);
                    $c = (int)$ur['count'];
                    if ($st === 'DRAFT') $metrics['my_drafts'] = $c;
                    if ($st === 'SUBMITTED') $metrics['my_submitted'] = $c;
                    if ($st === 'RETURNED') $metrics['my_returned'] = $c;
                }
            }
        } catch (\Throwable $e) {
            // In case of clean uninitialized database
        }

        return $metrics;
    }

    /**
     * Resolve recent requisitions for workbench display.
     */
    private function resolveRecentRequisitions(int $userId, array $roles): array
    {
        try {
            $query = "
                SELECT r.id, r.requisition_number, r.status, r.total_estimated_cost, r.fiscal_year,
                       r.created_at, r.submitted_at, pe.entity_name as entity_name, CONCAT(u.first_name, ' ', u.last_name) as requester_name
                FROM requisitions r
                JOIN planning_entities pe ON pe.id = r.planning_entity_id
                JOIN users u ON u.id = r.created_by
            ";

            if (in_array('REQUESTER', $roles, true) && !in_array('ADMIN', $roles, true) && !in_array('DEAN', $roles, true)) {
                $query .= " WHERE r.created_by = :uid ORDER BY r.created_at DESC LIMIT 10";
                $stmt = $this->db->prepare($query);
                $stmt->execute([':uid' => $userId]);
            } else {
                $query .= " ORDER BY r.created_at DESC LIMIT 10";
                $stmt = $this->db->query($query);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
