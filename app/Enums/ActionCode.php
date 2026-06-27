<?php

namespace App\Enums;

enum ActionCode: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case IMPORT = 'import';
    case EXPORT = 'export';
    case PRINT = 'print';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case ACTIVATE = 'activate';
    case DEACTIVATE = 'deactivate';
    case ASSIGN = 'assign';
    case VALIDATE = 'validate';
    case CANCEL = 'cancel';
    case UPLOAD = 'upload';
    case DOWNLOAD = 'download';
    case VIEW_SENSITIVE = 'view_sensitive';
    case UPDATE_SENSITIVE = 'update_sensitive';
}
