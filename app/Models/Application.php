<?php
class Application extends Model {
    protected string $table = 'applications';

    public function forListing(int $listingId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM applications WHERE listing_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$listingId]);
        return $stmt->fetch() ?: null;
    }

    public function recordSignature(string $signature, string $company, string $title): void {
        $stmt = $this->db->prepare("INSERT IGNORE INTO applied_signatures (signature, company, title) VALUES (?, ?, ?)");
        $stmt->execute([$signature, $company, $title]);
    }

    public function alreadyApplied(string $company, string $title): bool {
        $sig = Listing::makeAppliedSignature($company, $title);
        $stmt = $this->db->prepare("SELECT 1 FROM applied_signatures WHERE signature = ?");
        $stmt->execute([$sig]);
        return (bool)$stmt->fetchColumn();
    }
}
