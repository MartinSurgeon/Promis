<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Auth;

/**
 * Technical permission identifiers for Procurement Execution and Requisition domain.
 */
final class ExecutionPermissions
{
    public const VIEW = 'requisition.view';
    public const CREATE = 'requisition.create';
    public const EDIT = 'requisition.edit';
    public const SUBMIT = 'requisition.submit';
    public const ENDORSE = 'requisition.endorse';
    public const APPROVE = 'requisition.approve';
    public const RETURN_REQ = 'requisition.return';
    public const REJECT = 'requisition.reject';
    public const RECEIVE = 'requisition.receive';

    // Aliases matching statutory and schema conventions
    public const ALIAS_CREATE = 'req.create';
    public const ALIAS_VIEW = 'req.view';
    public const ALIAS_EDIT = 'req.edit';
    public const ALIAS_SUBMIT = 'req.submit';
    public const ALIAS_ENDORSE = 'req.endorse';
    public const ALIAS_APPROVE = 'req.approve';
    public const ALIAS_RETURN = 'req.return';
    public const ALIAS_REJECT = 'req.reject';
    public const ALIAS_RECEIVE = 'req.receive';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::SUBMIT,
            self::ENDORSE,
            self::APPROVE,
            self::RETURN_REQ,
            self::REJECT,
            self::RECEIVE,
        ];
    }
}
