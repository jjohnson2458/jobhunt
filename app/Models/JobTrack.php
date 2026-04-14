<?php
class JobTrack extends Model {
    protected string $table = 'job_tracks';

    public function active(): array {
        $stmt = $this->db->query("SELECT * FROM job_tracks WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
}
