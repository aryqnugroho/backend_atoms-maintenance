<?php

namespace App\Services;

use App\Exceptions\SignerNotAuthorizedException;
use App\Models\LocalUser;

/**
 * SignatureAuthorizationService — centralized role-based delegation logic.
 *
 * Role-Based Signature Delegation Rules:
 *
 * | Signer Role      | Can sign Manager slot | Can sign Supervisor slot | Can sign Technician/Repairer slot |
 * |------------------|-----------------------|--------------------------|-----------------------------------|
 * | Manager Teknik   | OWN only              | ALL                      | ALL                               |
 * | Supervisor       | ❌ NO                 | OWN only                 | ALL                               |
 * | Teknisi          | ❌ NO                 | ❌ NO                    | ALL                               |
 *
 * "OWN only" means the signer's name/id must match the target slot.
 * "ALL" means any user with that role can sign on behalf of the target.
 *
 * Immutability: if a slot is already signed → 409 (handled by HasSignature trait).
 * Audit: the actual signer info is stored in `*_signed_by_id`, `*_signed_by_name`, `*_signed_by_role`.
 */
class SignatureAuthorizationService
{
    /**
     * Determine the "slot type" from a role key.
     * Slot types: 'manager', 'supervisor', 'technician'
     */
    public static function slotType(string $roleKey): string
    {
        return match ($roleKey) {
            'mt', 'manager' => 'manager',
            'supervisor'    => 'supervisor',
            default         => 'technician', // technician, repairer, cnsd_officer, tfp_officer
        };
    }

    /**
     * Check if a signer is authorized to sign a given slot type.
     *
     * @param LocalUser $signer       The authenticated user attempting to sign
     * @param string    $slotType     'manager' | 'supervisor' | 'technician'
     * @param int|null  $targetId     The local_user ID of the intended signer (null if not set)
     * @param string|null $targetName The cached name of the intended signer
     *
     * @throws SignerNotAuthorizedException
     */
    public static function authorize(
        LocalUser $signer,
        string $slotType,
        ?int $targetId = null,
        ?string $targetName = null,
    ): void {
        $signerRole = self::signerCategory($signer);

        switch ($slotType) {
            case 'manager':
                // Only Manager Teknik can sign manager slots, and only their OWN
                if ($signerRole !== 'manager') {
                    throw new SignerNotAuthorizedException(
                        'Hanya Manager Teknik yang berhak menandatangani slot Manager.'
                    );
                }
                // Must be their own slot (id match or name match)
                self::assertOwnSlot($signer, $targetId, $targetName);
                break;

            case 'supervisor':
                // Manager can sign ANY supervisor slot (delegation)
                if ($signerRole === 'manager') {
                    return; // Allowed — delegation
                }
                // Supervisor can sign only their OWN slot
                if ($signerRole === 'supervisor') {
                    self::assertOwnSlot($signer, $targetId, $targetName);
                    return;
                }
                // Technician cannot sign supervisor slots
                throw new SignerNotAuthorizedException(
                    'Hanya Manager Teknik atau Supervisor yang berhak menandatangani slot Supervisor.'
                );

            case 'technician':
                // Manager and Supervisor can sign ANY technician slot (delegation)
                if ($signerRole === 'manager' || $signerRole === 'supervisor') {
                    return; // Allowed — delegation
                }
                // Technician can sign ANY technician slot (peer signing)
                if ($signerRole === 'technician') {
                    return; // Allowed
                }
                throw new SignerNotAuthorizedException(
                    'Anda tidak memiliki izin untuk menandatangani slot ini.'
                );

            default:
                throw new SignerNotAuthorizedException(
                    'Tipe slot tanda tangan tidak dikenali.'
                );
        }
    }

    /**
     * Determine the signer's category from their LocalUser role.
     */
    public static function signerCategory(LocalUser $signer): string
    {
        if ($signer->isManager() || $signer->isAdmin()) {
            return 'manager';
        }
        if ($signer->isSupervisor()) {
            return 'supervisor';
        }
        return 'technician';
    }

    /**
     * Assert that the signer is the actual target (own slot).
     * Uses ID match (priority) then tolerant name match (fallback).
     */
    private static function assertOwnSlot(
        LocalUser $signer,
        ?int $targetId,
        ?string $targetName,
    ): void {
        // If target ID is set, use ID comparison
        if ($targetId !== null && $targetId === $signer->id) {
            return; // Match by ID
        }

        // Fallback: tolerant name match
        if ($targetName !== null && WorkOrderService::namesMatch($targetName, $signer->name)) {
            return; // Match by name
        }

        // If both target ID and name are null, allow (no target specified)
        if ($targetId === null && ($targetName === null || $targetName === '')) {
            return;
        }

        throw new SignerNotAuthorizedException(sprintf(
            'Tanda tangan slot ini hanya dapat dilakukan oleh %s sendiri.',
            $targetName ?? 'penanda tangan yang ditunjuk'
        ));
    }

    /**
     * Check if the signing is a delegation (signer differs from target).
     */
    public static function isDelegated(
        LocalUser $signer,
        ?int $targetId,
        ?string $targetName,
    ): bool {
        if ($targetId !== null && $targetId === $signer->id) {
            return false; // Same person
        }
        if ($targetName !== null && WorkOrderService::namesMatch($targetName, $signer->name)) {
            return false; // Same person by name
        }
        // If no target set, not a delegation
        if ($targetId === null && ($targetName === null || $targetName === '')) {
            return false;
        }
        return true;
    }
}
