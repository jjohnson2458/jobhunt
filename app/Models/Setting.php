<?php
class Setting extends Model {
    protected string $table = 'settings';

    public function get(string $key, $default = null) {
        $stmt = $this->db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v === false ? $default : $v;
    }

    public function set(string $key, $value): void {
        $stmt = $this->db->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $stmt->execute([$key, (string)$value]);
    }

    public function all(): array {
        $stmt = $this->db->query("SELECT * FROM settings ORDER BY `key`");
        return $stmt->fetchAll();
    }
}
