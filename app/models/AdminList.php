<?php

namespace App\Models;

use PDO;

final class AdminList
{
    // SQL identifiers come only from the five models, never from request input.
    public static function page(PDO $pdo, string $from, string $columns, string $id, array $fields, array $filters): array
    {
        $where = [];
        $params = [];
        foreach ($fields as $field => $column) {
            if ($filters[$field] === '') {
                continue;
            }
            if ($field === 'q') {
                $where[] = "$column LIKE :keyword ESCAPE '!'";
                $params['keyword'] = '%' . strtr($filters[$field], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            } else {
                $where[] = "$column = :$field";
                $params[$field] = $filters[$field];
            }
        }
        $sql = $from . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $stmt = $pdo->prepare('SELECT COUNT(*)' . $sql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $filters['per_page']));
        $page = min($filters['page'], $pages);
        $stmt = $pdo->prepare("SELECT $columns$sql ORDER BY $id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $filters['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $filters['per_page'], PDO::PARAM_INT);
        $stmt->execute();
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
