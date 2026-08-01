# Additional diagnoses

This package verifies the optional diagnosis-list feature used when a Nakes
completes a consultation. The production flag is default-off and accepts at
most three diagnoses: one required primary diagnosis and two optional
secondary diagnoses.

The primary value remains in the legacy `medicalrecords.diagnosis` and
`konsultasi.diagnosa` columns. When the feature is enabled, the same primary
value plus any secondary values are written in order to
`medicalrecord_diagnoses` inside the caller-owned completion transaction.

Run the dependency-free unit suite with PHP 8.1:

```sh
php8.1 tools/diagnoses/tests/unit.php
```

Activation requires both variables and an exact staging runtime:

```text
DOCLINC_ADDITIONAL_DIAGNOSES_ENABLED=true
DOCLINC_ADDITIONAL_DIAGNOSES_ENVIRONMENT=staging
```

Do not enable the flag until `medicalrecord_diagnoses` from the existing care
operations foundation has been verified on the target environment.
