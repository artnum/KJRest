<?php
declare(strict_types=1);

namespace artnum\KJRest\Database;

use artnum\KJRest\Query\JSONQuery;
use LDAP\Connection;
use Exception;
use stdClass;
use Generator;

class LDAP implements IDatabase {
    const MAX_LDAP_FILTER_LENGTH = 40000;
    protected Connection $handle;
    protected string|null $table = '';
    protected string|null $base = '';
    protected string|null $rdn = '';

    function __construct(mixed $handle)
    {
        $this->table = null;
        $this->handle = $handle;    
    }

    function getHandle(): mixed
    {
        return $this->handle;
    }

    /* 
        Set the table to use for the next query.
        The table is a LDAP DN, with the last RDN being the ID.
        The ID is replaced with %s.
        Search will happen on ou=users,dc=exemple,dc=com.
        Example: uid=__ANYTHING__,ou=users,dc=example,dc=com
    */
    function setTable (string $table): void {
        $tparts = explode(',', $table);
        $rdn = array_shift($tparts);
        $this->base = implode(',', $tparts);
        $rdn = explode('=', $rdn, 2)[0];
        $this->rdn = $rdn;
        $this->table = sprintf('%s=%%s,%s', $rdn, $this->base);
    }

    function get(mixed $id): array|stdClass|null {
        if ($this->table === null) {
            throw new Exception('No table set', 500);
        }
        var_dump(sprintf($this->table, strval($id)));
        $result = ldap_read($this->handle, sprintf($this->table, strval($id)), '(objectclass=*)');
        if ($result === false) {
            throw new Exception('Object not found', 404);
        }

        if (ldap_count_entries($this->handle, $result) === 0) {
            throw new Exception('Object not found', 404);
        }

        $entry = ldap_first_entry($this->handle, $result);
        if (!$entry) {
            throw new Exception('Object not found', 404);
        }
        $outEntry = [];
        for($attr = ldap_first_attribute($this->handle, $entry); $attr !== false; $attr = ldap_next_attribute($this->handle, $entry)) {
            $values = ldap_get_values_len($this->handle, $entry, $attr);
            unset($values['count']);
            $outEntry[$attr] = array_values($values);
        }
        return $outEntry;
        
    }

    function create(array|stdClass $data): array|stdClass|null {
        if ($this->table === null) {
            throw new Exception('No table set', 500);
        }
        $id = $data[$this->rdn];
        if (is_array($id)) {
            $id = $id[0];
        }
        $results = ldap_add($this->handle,  sprintf($this->table, $id), $data);
        if ($results === false) {
            throw new Exception('Create failed', 500, 
                new Exception(ldap_error($this->handle), ldap_errno($this->handle)));
        }
        return $this->get($id);
    }

    function rename (mixed $id, mixed $newId): bool {
        if ($this->table === null) {
            throw new Exception('No table set', 500);
        }
        return ldap_rename($this->handle, sprintf($this->table, strval($id)), $this->rdn . '=' . strval($newId), $this->base, true);
    }

    function update(mixed $id, array|stdClass $data): array|stdClass|null {
        if ($this->table === null) {
            throw new Exception('No table set', 500);
        }
        
        foreach ($data as $k => $v) {
            if ($k === $this->rdn) {
                if (!$this->rename($id, $v)) {
                    throw new Exception('Rename failed', 500, 
                    new Exception(ldap_error($this->handle), ldap_errno($this->handle)));
                }
                $id = $v;
                continue;
            }
            $original = $this->get($id);

            $mods = [];
            if (strpos($k, '-') === 0 && isset($original[substr($k, 1)])) {
                $mods[] = ['modtype' => LDAP_MODIFY_BATCH_REMOVE_ALL, 'attrib' => substr($k, 1)];
                continue;
            }
            if (isset($original[$k])) {
                if (!is_array($v)) { $v = [$v]; }
                $mods[] = ['modtype' => LDAP_MODIFY_BATCH_REPLACE, 'attrib' => $k, 'values' => $v];
                continue;
            }
            if (!is_array($v)) { $v = [$v]; }
            $mods[] = ['modtype' => LDAP_MODIFY_BATCH_ADD, 'attrib' =>  $k, 'values' => $v];
        }
        if (!empty($mods)) {
            $result = ldap_modify_batch($this->handle, sprintf($this->table, strval($id)), $mods);
        }

        return $this->get($id);
    }

    function delete(mixed $id): void {
        if ($this->table === null) {
            throw new Exception('No table set', 500);
        }
        ldap_delete($this->handle, sprintf($this->table, $id));
    }

    function toFilter (array $query, $root = true): string {
        $filter = '';
        var_dump($query);
        if (count($query) === 0) {
            return '(objectclass=*)';
        }
        foreach ($query as $k => $v) {
            switch($k) {
                case '#and':
                    return '(&' . $this->toFilter($v, false) . ')';
                case '#or':
                    return '(|' . $this->toFilter($v, false) . ')';
                case '#not':
                    return '(!' . $this->toFilter($v, false) . ')';
                default:
                    if ($root) {
                        return '(&' . $this->toFilter([$k => $v], false) . ')';
                    }
                    break;
            }

            if (!is_array($v[1])) { $v[1] = [$v[1]]; }
            foreach ($v[1] as $value) {
                $value = ldap_escape(strval($value), '', LDAP_ESCAPE_FILTER);
                $k = ldap_escape(strval($k), '', LDAP_ESCAPE_FILTER);
                switch ($v[0]) {
                    case '>=':
                    case '<=':
                    case '>':
                    case '<':
                    case '=':   $filter    .= "($k$v[0]$value)";    break;
                    case '!=':  $filter    .= "(!($k=$value))";     break;
                    case '~':   $filter    .= "($k=~$value)";       break;
                    case '~~':  $filter    .= "($k=*$value*)";      break;
                    case '>~':  $filter    .= "($k=$value*)";       break;
                    case '<~':  $filter    .= "($k=*$value)";       break;
                    case '!~~': $filter    .= "(!($k=*$value*))";   break;
                    case '!>~': $filter    .= "(!($k=$value*))";    break;
                    case '!<~': $filter    .= "(!($k=*$value))";    break;
                    case '-':   
                    case '--':  $filter    .= "(!($k=*))";         break;
                    case '*':
                    case '**':  $filter    .= "($k=*)";            break;
                }
            }            
        }
        return $filter;
    }

    function query(JSONQuery $query): Generator {
        if ($this->table === null) {
            throw new Exception('No table set', 500);
        }
        $filter = $this->toFilter($query->getQuery());
        var_dump($filter);
        $filterLen = strlen($filter);
        if ($filterLen >= self::MAX_LDAP_FILTER_LENGTH) {
            throw new Exception('Search query too long for database', 413);
        }
        $result = ldap_list($this->handle, $this->base, $filter);
        if ($result === false) {
            throw new Exception('Search failed', 500);
        }
        if (ldap_count_entries($this->handle, $result) === 0) {
            return;
        }

        for($entry = ldap_first_entry($this->handle, $result); $entry !== false; $entry = ldap_next_entry($this->handle, $entry)) {
            $outEntry = [];
            for($attr = ldap_first_attribute($this->handle, $entry); $attr !== false; $attr = ldap_next_attribute($this->handle, $entry)) {
                $values = ldap_get_values_len($this->handle, $entry, $attr);
                unset($values['count']);
                $outEntry[$attr] = array_values($values);
            }
            yield $outEntry;
        }
    }
}