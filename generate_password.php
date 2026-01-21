<?php
// Generate proper password hashes
echo "=== Proper Password Hashes ===\n\n";

// Admin password
$admin_pass = 'Admin@2024';
$admin_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
echo "Admin Password: $admin_pass\n";
echo "Admin Hash: $admin_hash\n\n";

// Voter password
$voter_pass = 'Voter@2024';
$voter_hash = password_hash($voter_pass, PASSWORD_DEFAULT);
echo "Voter Password: $voter_pass\n";
echo "Voter Hash: $voter_hash\n\n";

// Verify they work
echo "=== Verification Test ===\n";
echo "Admin verify test: " . (password_verify($admin_pass, $admin_hash) ? "✓ PASS" : "✗ FAIL") . "\n";
echo "Voter verify test: " . (password_verify($voter_pass, $voter_hash) ? "✓ PASS" : "✗ FAIL") . "\n";
?>

=== Proper Password Hashes === Admin Password: Admin@2024 Admin Hash: $2y$10$sSrO9uIvZ0L8e7YHBuctMeOP2FJx/.avmSgEM28Dn/RImOBWePjOO Voter Password: Voter@2024 Voter Hash: $2y$10$2EEyLoHZE1nM2pfNwz4PvuLlZzpTOjVVsM55R3LWC3pEPWSt9c/QC === Verification Test === Admin verify test: ✓ PASS Voter verify test: ✓ PASS