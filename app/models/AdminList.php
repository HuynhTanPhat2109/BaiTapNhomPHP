<?php

namespace App\Models;

use PDO;

final class AdminList
{
    // SQL identifiers, scope conditions and ordering come from models, never request input.
    public static function page(PDO $pdo, string $from, string $columns, string $id, array $fields, array $filters,
        array $where = [], array $params = [], ?string $order = null): array
    {
        foreach ($fields as $field => $column) {
            if ($filters[$field] === '') {
                continue;
            }
            if ($field === 'q') {
                $search = [];
                foreach ((array) $column as $index => $searchColumn) {
                    $key = is_array($column) ? 'keyword' . $index : 'keyword';
                    $search[] = "$searchColumn LIKE :$key ESCAPE '!'";
                    $params[$key] = '%' . strtr($filters[$field], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
                }
                $where[] = count($search) > 1 ? '(' . implode(' OR ', $search) . ')' : $search[0];
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
        $order ??= "$id DESC";
        $stmt = $pdo->prepare("SELECT $columns$sql ORDER BY $order LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $filters['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $filters['per_page'], PDO::PARAM_INT);
        $stmt->execute();
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
