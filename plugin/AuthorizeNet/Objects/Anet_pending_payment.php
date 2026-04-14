<?php

require_once dirname(__FILE__) . '/../../../videos/configuration.php';

class Anet_pending_payment extends ObjectYPT
{
    protected $id, $ref_id, $users_id, $plans_id, $amount, $currency, $status, $transaction_id, $metadata_json, $attempts, $last_checked_php_time, $created_php_time, $modified_php_time, $error_text;

    private static function enableInternalSaveBypass(): void
    {
        global $global;
        $global['bypassSameDomainCheck'] = 1;
    }

    static function getSearchFieldsNames()
    {
        return ['ref_id', 'status', 'transaction_id', 'currency', 'error_text'];
    }

    static function getTableName()
    {
        return 'anet_pending_payment';
    }

    function setId($id) { $this->id = intval($id); }
    function setRef_id($ref_id) { $this->ref_id = $ref_id; }
    function setUsers_id($users_id) { $this->users_id = intval($users_id); }
    function setPlans_id($plans_id) { $this->plans_id = intval($plans_id); }
    function setAmount($amount) { $this->amount = floatval($amount); }
    function setCurrency($currency) { $this->currency = $currency; }
    function setStatus($status) { $this->status = $status; }
    function setTransaction_id($transaction_id) { $this->transaction_id = $transaction_id; }
    function setMetadata_json($metadata_json) { $this->metadata_json = $metadata_json; }
    function setAttempts($attempts) { $this->attempts = intval($attempts); }
    function setLast_checked_php_time($last_checked_php_time) { $this->last_checked_php_time = intval($last_checked_php_time); }
    function setCreated_php_time($created_php_time) { $this->created_php_time = intval($created_php_time); }
    function setModified_php_time($modified_php_time) { $this->modified_php_time = intval($modified_php_time); }
    function setError_text($error_text) { $this->error_text = $error_text; }

    function getId() { return intval($this->id); }
    function getRef_id() { return $this->ref_id; }
    function getUsers_id() { return intval($this->users_id); }
    function getPlans_id() { return intval($this->plans_id); }
    function getAmount() { return floatval($this->amount); }
    function getCurrency() { return $this->currency; }
    function getStatus() { return $this->status; }
    function getTransaction_id() { return $this->transaction_id; }
    function getMetadata_json() { return $this->metadata_json; }
    function getAttempts() { return intval($this->attempts); }
    function getLast_checked_php_time() { return intval($this->last_checked_php_time); }
    function getCreated_php_time() { return intval($this->created_php_time); }
    function getModified_php_time() { return intval($this->modified_php_time); }
    function getError_text() { return $this->error_text; }

    static function getFromRefId(string $refId)
    {
        $sql = "SELECT * FROM " . static::getTableName() . " WHERE ref_id = ? LIMIT 1";
        $res = sqlDAL::readSql($sql, "s", [$refId]);
        $data = sqlDAL::fetchAssoc($res);
        sqlDAL::close($res);
        return $data ?: false;
    }

    static function getOpenByUsersId(int $users_id, int $limit = 10): array
    {
        $sql = "SELECT * FROM " . static::getTableName() . " WHERE users_id = ? AND status IN ('pending','reconciling') ORDER BY id DESC LIMIT ?";
        $res = sqlDAL::readSql($sql, "ii", [$users_id, $limit]);
        $data = sqlDAL::fetchAllAssoc($res);
        sqlDAL::close($res);
        return $data ?: [];
    }

    static function getRecentOpen(int $limit = 20, int $maxAgeSeconds = 3600): array
    {
        $minTime = time() - max(60, $maxAgeSeconds);
        $sql = "SELECT * FROM " . static::getTableName() . " WHERE status IN ('pending','reconciling') AND created_php_time >= ? ORDER BY id ASC LIMIT ?";
        $res = sqlDAL::readSql($sql, "ii", [$minTime, $limit]);
        $data = sqlDAL::fetchAllAssoc($res);
        sqlDAL::close($res);
        return $data ?: [];
    }

    static function createPending(string $refId, int $users_id, float $amount, array $metadata = [], string $currency = 'USD'): int
    {
        self::enableInternalSaveBypass();
        $obj = new self();
        $obj->setRef_id($refId);
        $obj->setUsers_id($users_id);
        $obj->setPlans_id((int)($metadata['plans_id'] ?? 0));
        $obj->setAmount($amount);
        $obj->setCurrency($currency);
        $obj->setStatus('pending');
        $obj->setMetadata_json(_json_encode($metadata));
        $obj->setAttempts(0);
        $obj->setLast_checked_php_time(0);
        $obj->setCreated_php_time(time());
        $obj->setModified_php_time(time());
        $obj->setError_text('');
        return (int)$obj->save();
    }

    static function markChecked(int $id, string $status = 'reconciling', string $errorText = ''): bool
    {
        self::enableInternalSaveBypass();
        $obj = new self($id);
        if (empty($obj->getId())) {
            return false;
        }
        $obj->setStatus($status);
        $obj->setAttempts($obj->getAttempts() + 1);
        $obj->setLast_checked_php_time(time());
        $obj->setModified_php_time(time());
        if ($errorText !== '') {
            $obj->setError_text($errorText);
        }
        return (bool)$obj->save();
    }

    static function markProcessedById(int $id, string $transactionId): bool
    {
        self::enableInternalSaveBypass();
        $obj = new self($id);
        if (empty($obj->getId())) {
            return false;
        }
        $obj->setStatus('processed');
        $obj->setTransaction_id($transactionId);
        $obj->setLast_checked_php_time(time());
        $obj->setModified_php_time(time());
        $obj->setError_text('');
        return (bool)$obj->save();
    }

    static function markProcessedByRefId(string $refId, string $transactionId): bool
    {
        $row = self::getFromRefId($refId);
        if (empty($row['id'])) {
            return false;
        }
        return self::markProcessedById((int)$row['id'], $transactionId);
    }
}
