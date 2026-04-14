<?php
class Listing extends Model {
    protected string $table = 'listings';

    public static function makeDedupeHash(string $title, string $company, ?string $location): string {
        $norm = fn($s) => preg_replace('/\s+/', ' ', strtolower(trim((string)$s)));
        return sha1($norm($title) . '|' . $norm($company) . '|' . $norm($location));
    }

    public static function makeAppliedSignature(string $company, string $title): string {
        $norm = fn($s) => preg_replace('/\s+/', ' ', strtolower(trim((string)$s)));
        return sha1($norm($company) . '|' . $norm($title));
    }

    /**
     * Insert a listing if its dedupe_hash doesn't exist. Returns [id, isNew].
     */
    public function upsert(array $data): array {
        $hash = self::makeDedupeHash($data['title'], $data['company'], $data['location'] ?? null);
        $existing = $this->db->prepare("SELECT id FROM listings WHERE dedupe_hash = ?");
        $existing->execute([$hash]);
        if ($row = $existing->fetch()) {
            return ['id' => (int)$row['id'], 'isNew' => false];
        }
        $data['dedupe_hash'] = $hash;
        $cols = array_keys($data);
        $place = array_map(fn($c) => ":$c", $cols);
        $sql = "INSERT INTO listings (" . implode(',', $cols) . ") VALUES (" . implode(',', $place) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return ['id' => (int)$this->db->lastInsertId(), 'isNew' => true];
    }

    public function search(array $filters = [], int $limit = 100): array {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['track_id'])) { $where[] = 'track_id = ?'; $params[] = $filters['track_id']; }
        if (!empty($filters['status']))   { $where[] = 'status = ?';   $params[] = $filters['status']; }
        if (!empty($filters['source']))   { $where[] = 'source = ?';   $params[] = $filters['source']; }
        if (!empty($filters['min_score'])){ $where[] = 'score >= ?';   $params[] = (int)$filters['min_score']; }
        $sql = "SELECT * FROM listings WHERE " . implode(' AND ', $where) . " ORDER BY COALESCE(score,0) DESC, fetched_at DESC LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function counts(): array {
        $stmt = $this->db->query("SELECT status, COUNT(*) c FROM listings GROUP BY status");
        $out = ['new'=>0,'reviewed'=>0,'starred'=>0,'hidden'=>0,'blacklisted'=>0,'duplicate'=>0];
        foreach ($stmt->fetchAll() as $r) { $out[$r['status']] = (int)$r['c']; }
        return $out;
    }
}
