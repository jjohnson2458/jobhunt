<?php
class ScraperRun extends Model {
    protected string $table = 'scraper_runs';

    public function recent(int $limit = 50): array {
        $stmt = $this->db->query("SELECT * FROM scraper_runs ORDER BY id DESC LIMIT " . (int)$limit);
        return $stmt->fetchAll();
    }

    public function start(string $source, ?int $trackId): int {
        $stmt = $this->db->prepare("INSERT INTO scraper_runs (source, track_id) VALUES (?, ?)");
        $stmt->execute([$source, $trackId]);
        return (int)$this->db->lastInsertId();
    }

    public function finish(int $id, string $status, int $found, int $new, ?string $error = null, ?string $log = null): void {
        $stmt = $this->db->prepare("UPDATE scraper_runs SET finished_at=NOW(), status=?, listings_found=?, listings_new=?, error_message=?, raw_log=? WHERE id=?");
        $stmt->execute([$status, $found, $new, $error, $log, $id]);
    }
}
