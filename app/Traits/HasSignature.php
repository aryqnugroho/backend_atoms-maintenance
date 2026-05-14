<?php

namespace App\Traits;

use App\Models\LocalUser;
use InvalidArgumentException;
use RuntimeException;

trait HasSignature
{
    /**
     * Save an immutable base64 PNG signature for a role.
     */
    public function saveSignature(string $role, string $base64, int $userId): void
    {
        $role = $this->normalizeSignatureRole($role);
        $map = $this->getSignatureRoleMap();

        if (!array_key_exists($role, $map)) {
            throw new InvalidArgumentException("Unsupported signature role: {$role}");
        }

        $columns = $map[$role];
        $signatureColumn = $columns['signature'];

        if (!empty($this->{$signatureColumn})) {
            throw new RuntimeException("Signature for role {$role} has already been saved.");
        }

        $this->validateBase64PngSignature($base64);

        $user = LocalUser::find($userId);
        if (!$user) {
            throw new InvalidArgumentException('Signer user was not found.');
        }

        $this->{$signatureColumn} = $base64;

        if (!empty($columns['signed_at'])) {
            $this->{$columns['signed_at']} = now();
        }

        if (!empty($columns['signed_by'])) {
            $this->{$columns['signed_by']} = $userId;
        }

        if (!empty($columns['name']) && empty($this->{$columns['name']})) {
            $this->{$columns['name']} = $user->name;
        }

        if (method_exists($this, 'beforeSignatureStatusRecalculated')) {
            $this->beforeSignatureStatusRecalculated($role, $user);
        }

        if ($this->hasAttributeColumn('status')) {
            $this->status = $this->recalculateStatus();
        }

        $this->save();
    }

    /**
     * Return signature roles required before this model is complete.
     */
    public function getRequiredSignatures(): array
    {
        if (method_exists($this, 'requiredSignatureRoles')) {
            return array_values($this->requiredSignatureRoles());
        }

        $roles = array_keys($this->getSignatureRoleMap());

        if (
            property_exists($this, 'has_supervisor') ||
            array_key_exists('has_supervisor', $this->getAttributes())
        ) {
            $hasSupervisor = (bool) $this->has_supervisor;
            if (!$hasSupervisor) {
                $roles = array_values(array_filter(
                    $roles,
                    fn (string $role): bool => $role !== 'supervisor'
                ));
            }
        }

        return $roles;
    }

    /**
     * Determine whether all required roles have signed.
     */
    public function isComplete(): bool
    {
        return $this->getPendingSignatures() === [];
    }

    /**
     * Return required roles that have not signed yet.
     */
    public function getPendingSignatures(): array
    {
        $map = $this->getSignatureRoleMap();
        $pending = [];

        foreach ($this->getRequiredSignatures() as $role) {
            if (!isset($map[$role])) {
                continue;
            }

            $signatureColumn = $map[$role]['signature'];
            if (empty($this->{$signatureColumn})) {
                $pending[] = $role;
            }
        }

        return $pending;
    }

    /**
     * Recalculate signature-derived status.
     */
    public function recalculateStatus(): string
    {
        if ($this->isComplete()) {
            return 'completed';
        }

        if (method_exists($this, 'isShiftEnded') && $this->isShiftEnded()) {
            return 'on_hold';
        }

        return 'ongoing';
    }

    /**
     * Validate the base64 PNG data URL required by signature endpoints.
     */
    protected function validateBase64PngSignature(string $base64): void
    {
        $prefix = 'data:image/png;base64,';

        if (!str_starts_with($base64, $prefix)) {
            throw new InvalidArgumentException('Signature must be a base64 PNG data URL.');
        }

        $payload = substr($base64, strlen($prefix));
        if ($payload === '') {
            throw new InvalidArgumentException('Signature payload cannot be empty.');
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false || $decoded === '') {
            throw new InvalidArgumentException('Signature payload is not valid base64.');
        }
    }

    /**
     * Map supported role names to model columns.
     */
    protected function getSignatureRoleMap(): array
    {
        $default = [
            'mt' => [
                'name' => 'mt_name',
                'signature' => 'mt_signature',
                'signed_at' => 'mt_signed_at',
                'signed_by' => 'mt_signed_by',
            ],
            'supervisor' => [
                'name' => 'supervisor_name',
                'signature' => 'supervisor_signature',
                'signed_at' => 'supervisor_signed_at',
                'signed_by' => 'supervisor_signed_by',
            ],
            'technician' => [
                'name' => 'technician_name',
                'signature' => 'technician_signature',
                'signed_at' => 'technician_signed_at',
                'signed_by' => 'technician_signed_by',
            ],
            'cnsd_officer' => [
                'name' => 'cnsd_officer_name',
                'signature' => 'cnsd_officer_signature',
                'signed_at' => 'cnsd_officer_signed_at',
                'signed_by' => 'cnsd_officer_signed_by',
            ],
            'tfp_officer' => [
                'name' => 'tfp_officer_name',
                'signature' => 'tfp_officer_signature',
                'signed_at' => 'tfp_officer_signed_at',
                'signed_by' => 'tfp_officer_signed_by',
            ],
        ];

        if (method_exists($this, 'signatureRoleMap')) {
            return array_replace_recursive($default, $this->signatureRoleMap());
        }

        return $default;
    }

    /**
     * Normalize accepted API aliases to canonical role keys.
     */
    protected function normalizeSignatureRole(string $role): string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'cnsd' => 'cnsd_officer',
            'tfp' => 'tfp_officer',
            default => $role,
        };
    }

    /**
     * Check a model attribute exists without requiring a DB schema lookup.
     */
    protected function hasAttributeColumn(string $column): bool
    {
        return array_key_exists($column, $this->getAttributes()) ||
            in_array($column, $this->getFillable(), true);
    }
}
