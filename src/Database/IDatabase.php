<?php
declare(strict_types=1);

namespace artnum\KJRest\Database;

use artnum\KJRest\Query\JSONQuery;
use Generator;
use stdClass;

interface IDatabase {
    function __construct(mixed $handle);
    function getHandle(): mixed;
    function setTable (string $table): void;
    function get(mixed $id): array|stdClass|null;
    function create(array|stdClass $data):array|stdClass|null;
    function update(mixed $id, array|stdClass $data): array|stdClass|null;
    function delete(mixed $id): void;
    function query(JSONQuery $query): Generator;
}