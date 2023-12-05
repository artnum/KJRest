<?php
declare(strict_types=1);

namespace artnum\KJRest\HTTP;

interface IRequest {
    function getEndpoint(): string|null;
    function getItem(): string|null;
    function getRPC(): string|null;
    function getVerb(): string;
    function getBody(): array;
    function getBase(): string;
}
