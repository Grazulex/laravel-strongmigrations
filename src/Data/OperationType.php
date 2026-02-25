<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Data;

enum OperationType: string
{
    case AddColumn = 'add_column';
    case RemoveColumn = 'remove_column';
    case RenameColumn = 'rename_column';
    case ChangeColumn = 'change_column';
    case AddIndex = 'add_index';
    case RemoveIndex = 'remove_index';
    case AddForeignKey = 'add_foreign_key';
    case RemoveForeignKey = 'remove_foreign_key';
    case CreateTable = 'create_table';
    case DropTable = 'drop_table';
    case RenameTable = 'rename_table';
    case AddUniqueConstraint = 'add_unique_constraint';
    case AddCheckConstraint = 'add_check_constraint';
    case SetNotNull = 'set_not_null';
    case Backfill = 'backfill';
    case RawSql = 'raw_sql';
}
