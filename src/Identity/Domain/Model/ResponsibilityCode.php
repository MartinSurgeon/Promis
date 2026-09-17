<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\Model;

/**
 * Authoritative Catalog of Individual Roles and Responsibilities in PROMIS.
 * Each responsibility code represents a distinct operational permission.
 */
final class ResponsibilityCode
{
    public const CREATE_SUBMIT_OWN = 'CREATE_SUBMIT_OWN';
    public const APPROVE_OWN = 'APPROVE_OWN';
    public const RECOMMEND_DEPT = 'RECOMMEND_DEPT';
    public const APPROVE_FACULTY_DEPTS = 'APPROVE_FACULTY_DEPTS';
    public const APPROVE_SUBORDINATE_UNITS = 'APPROVE_SUBORDINATE_UNITS';
    public const PREPARE_PROCUREMENT_PLANS = 'PREPARE_PROCUREMENT_PLANS';
    public const RETURN_REQUESTS = 'RETURN_REQUESTS';
    public const REJECT_REQUESTS = 'REJECT_REQUESTS';
    public const APPROVE_FINANCIAL_COMMITMENTS = 'APPROVE_FINANCIAL_COMMITMENTS';
    public const RECEIVE_PURCHASED_ITEMS = 'RECEIVE_PURCHASED_ITEMS';
    public const APPROVE_PROCUREMENT_PLANS = 'APPROVE_PROCUREMENT_PLANS';
    public const MANAGE_USERS_SYSTEM = 'MANAGE_USERS_SYSTEM';

    /**
     * Complete Responsibility Catalog definition.
     *
     * @return array<string, array{code: string, label: string, description: string, is_governance: bool}>
     */
    public static function all(): array
    {
        return [
            self::CREATE_SUBMIT_OWN => [
                'code' => self::CREATE_SUBMIT_OWN,
                'label' => 'Create and submit own requests',
                'description' => 'Author purchase requests and submit them for review and approval.',
                'is_governance' => false,
            ],
            self::APPROVE_OWN => [
                'code' => self::APPROVE_OWN,
                'label' => 'Approve own requests',
                'description' => 'Explicit authorization allowing an officer to approve requisitions they personally initiated, subject to stage and area scope.',
                'is_governance' => true,
            ],
            self::RECOMMEND_DEPT => [
                'code' => self::RECOMMEND_DEPT,
                'label' => 'Recommend requests from own department',
                'description' => 'Review and endorse departmental purchase requests for faculty escalation.',
                'is_governance' => false,
            ],
            self::APPROVE_FACULTY_DEPTS => [
                'code' => self::APPROVE_FACULTY_DEPTS,
                'label' => 'Approve requests from departments under the assigned faculty',
                'description' => 'Executive approval authority over member departments under the assigned faculty.',
                'is_governance' => false,
            ],
            self::APPROVE_SUBORDINATE_UNITS => [
                'code' => self::APPROVE_SUBORDINATE_UNITS,
                'label' => 'Approve requests from subordinate units',
                'description' => 'Review and approve requests originating from subordinate cost centers or units.',
                'is_governance' => false,
            ],
            self::PREPARE_PROCUREMENT_PLANS => [
                'code' => self::PREPARE_PROCUREMENT_PLANS,
                'label' => 'Prepare procurement plans',
                'description' => 'Create and edit annual departmental procurement plans and item schedules.',
                'is_governance' => false,
            ],
            self::RETURN_REQUESTS => [
                'code' => self::RETURN_REQUESTS,
                'label' => 'Return requests',
                'description' => 'Send requests back to the originator for modification or clarification.',
                'is_governance' => false,
            ],
            self::REJECT_REQUESTS => [
                'code' => self::REJECT_REQUESTS,
                'label' => 'Reject requests',
                'description' => 'Formally reject unfeasible or unauthorized requisitions with justification.',
                'is_governance' => false,
            ],
            self::APPROVE_FINANCIAL_COMMITMENTS => [
                'code' => self::APPROVE_FINANCIAL_COMMITMENTS,
                'label' => 'Approve financial commitments',
                'description' => 'Verify institutional budget allocation and commit funds for procurement.',
                'is_governance' => false,
            ],
            self::RECEIVE_PURCHASED_ITEMS => [
                'code' => self::RECEIVE_PURCHASED_ITEMS,
                'label' => 'Receive purchased items',
                'description' => 'Inspect and record receipt of goods and services delivered by suppliers.',
                'is_governance' => false,
            ],
            self::APPROVE_PROCUREMENT_PLANS => [
                'code' => self::APPROVE_PROCUREMENT_PLANS,
                'label' => 'Approve procurement plans',
                'description' => 'Review, approve, and consolidate institutional procurement plan packages.',
                'is_governance' => false,
            ],
            self::MANAGE_USERS_SYSTEM => [
                'code' => self::MANAGE_USERS_SYSTEM,
                'label' => 'Manage staff accounts and system administration',
                'description' => 'Create staff accounts, assign areas, grant responsibilities, and oversee system setup.',
                'is_governance' => false,
            ],
        ];
    }

    public static function isValid(string $code): bool
    {
        return array_key_exists($code, self::all());
    }

    public static function getLabel(string $code): string
    {
        $all = self::all();
        return $all[$code]['label'] ?? $code;
    }
}
