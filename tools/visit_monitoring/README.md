# Puskesmas visit monitoring

Command-center accounts can monitor an accepted request assigned to a linked
personal Nakes in the same Puskesmas. Monitoring is read-only: the command
center can read the latest Nakes/patient coordinates and route projection, but
cannot submit device coordinates or change visit status.

The map polls while its offcanvas panel is open and stops when the panel is
closed or the page unloads. Personal Nakes retain the existing operator flow.

Run the dependency-free policy and source contract suite:

```sh
php8.1 tools/visit_monitoring/tests/unit.php
```
