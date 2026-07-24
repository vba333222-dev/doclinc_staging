<?php

class ClinicalPackageChecksumBuilder
{
	const PROFILE = 'doclink-package-jcs-v1';

	private $canonicalizer;

	public function __construct(JcsCanonicalizer $canonicalizer)
	{
		$this->canonicalizer = $canonicalizer;
	}

	/*
	 * Normative doclink-package-jcs-v1 object:
	 * {
	 *   "profile": "doclink-package-jcs-v1",
	 *   "package": <normalized source-owned package declarations>,
	 *   "package_capabilities": <canonical-JSON-byte-sorted blocked capability plans>,
	 *   "datasets": [
	 *     {
	 *       "registration": <normalized dataset declarations and observed checksum/count>,
	 *       "source_evidence": <source/provenance declarations>,
	 *       "capabilities": <canonical-JSON-byte-sorted blocked capability plans>,
	 *       "field_contracts": <canonical-JSON-byte-sorted observed contracts>
	 *     }
	 *   ],
	 *   "governance": <review, dependency, runtime, and submanifest declarations>
	 * }
	 *
	 * Datasets are sorted by binary dataset_key. Unordered capability, field,
	 * dependency, status-mapping, notice, and submanifest collections are sorted
	 * by the canonical JSON bytes before serialization. The canonicalizer is the
	 * RFC 8785-compatible, integer-only subset defined by this profile; floats are
	 * rejected. Absolute paths, manifest raw-byte checksum, mtimes, modes, owners,
	 * database IDs/state, and generated timestamps are absent.
	 */
	public function checksum(array $projection)
	{
		if (!isset($projection['profile']) || $projection['profile'] !== self::PROFILE) {
			throw new ClinicalPackageException('package_checksum_profile_invalid', 'Semantic package checksum profile is invalid.');
		}
		return hash('sha256', $this->canonicalizer->canonicalize($projection));
	}

	public function canonicalJson(array $projection)
	{
		return $this->canonicalizer->canonicalize($projection);
	}
}
