<?php
if (PHP_SAPI !== 'cli') exit('No direct script access allowed');
echo "PLACEMENT_INVENTORY_MODE=READ_ONLY\n";
echo "REPORTS=no_canonical_placement,multiple_active_placements,facility_mismatch,linked_user_remark_mismatch,invalid_facility,command_center_misuse,duplicate_transfer_intent\n";
echo "DATABASE_WRITE_EXECUTED=false\n";
