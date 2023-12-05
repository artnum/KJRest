<?php
declare(strict_types=1);

namespace artnum\KJRest;

use Generator;

interface IEndpoint {
    function __construct(HTTP\IRequest $request);
    function init(): void;
    function run(DBWallet $dbwallet): Generator;
}