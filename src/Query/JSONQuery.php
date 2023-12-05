<?php
declare(strict_types=1);

namespace artnum\KJRest\Query;

use stdClass;

class JSONQuery {
    protected array $query;
    function __construct(array|stdClass $query)
    {
        if ($query instanceof stdClass) {
            $query = (array) $query;
        }
        $this->query = $this->normalize($query);
    }

    function normalize (array $query): array {
        $normalized = [];
        foreach ($query as $k => $v) {
            $kparts = explode(':', $k, 2);
            if (count($kparts) === 2) {
                $k = $kparts[0];
                $normalized[$k] = [];
            } else {
                $normalized[$k] = null;
            }
            if (!is_array($v) || count($v) === 1) {
                switch ($v) {
                    case '*';
                    case '**':
                    case '-':
                    case '--':
                        if (is_array($normalized[$k])) {
                            $normalized[$k][] = [$v, '', 'str'];
                            continue 2;                
                        }
                        $normalized[$k] = [$v, '', 'str'];
                        continue 2;
                }
                if (is_array($normalized[$k])) {
                    $normalized[$k][] = ['=', strval($v), 'str'];
                    continue;                
                }
                $normalized[$k] = ['=', strval($v), 'str'];
                continue;
            }
            if (count($v) === 2) {
                if (is_array($normalized[$k])) {
                    $normalized[$k][] = [$v[0], strval($v[1]), 'str'];
                    continue;                
                }
                $normalized[$k] = [$v[0], strval($v[1]), 'str'];
                continue;
            }
            if (is_array($normalized[$k])) {
                $normalized[$k][] = $v;
                continue;
            }
            $normalized[$k] = $v;
        }
        return $normalized;
    }

    function getQuery(): array {
        return $this->query;
    }
}