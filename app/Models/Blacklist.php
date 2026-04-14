<?php
class Blacklist extends Model {
    protected string $table = 'blacklist';

    public function all(): array {
        $stmt = $this->db->query("SELECT * FROM blacklist ORDER BY type, pattern");
        return $stmt->fetchAll();
    }

    public function matches(string $company, string $title, string $description, ?int $trackId = null): ?array {
        $stmt = $this->db->prepare("SELECT * FROM blacklist WHERE track_id IS NULL OR track_id = ?");
        $stmt->execute([$trackId]);
        $haystack = strtolower("$company\n$title\n$description");
        foreach ($stmt->fetchAll() as $b) {
            $needle = strtolower($b['pattern']);
            if ($b['type'] === 'company' && str_contains(strtolower($company), $needle)) return $b;
            if ($b['type'] === 'keyword' && str_contains($haystack, $needle))           return $b;
            if ($b['type'] === 'recruiter' && str_contains(strtolower($company), $needle)) return $b;
            if ($b['type'] === 'domain' && str_contains($haystack, $needle))             return $b;
        }
        return null;
    }
}
