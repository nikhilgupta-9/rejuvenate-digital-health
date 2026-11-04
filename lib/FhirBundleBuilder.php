<?php

/**
 * FhirBundleBuilder — Builds NDHM FHIR R4 Document Bundles for ABDM Health Information Exchange.
 *
 * Implements ABDM / NRCES FHIR R4 Profiles:
 *   - DocumentBundle: https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle
 *   - PrescriptionRecord: https://nrces.in/ndhm/fhir/r4/StructureDefinition/PrescriptionRecord
 *   - HealthDocumentRecord: https://nrces.in/ndhm/fhir/r4/StructureDefinition/HealthDocumentRecord
 *     (Phase 1 of the multi-hiType rollout)
 *   - DiagnosticReportRecord: https://nrces.in/ndhm/fhir/r4/StructureDefinition/DiagnosticReportRecord
 *     (Phase 2)
 *   - OPConsultRecord: https://nrces.in/ndhm/fhir/r4/StructureDefinition/OPConsultRecord
 *     (Phase 3 — DischargeSummary / ImmunizationRecord / WellnessRecord are later phases, same pattern.)
 *
 * Assembles consultation, prescription, doctor, patient, vitals and diagnosis data
 * into a fully valid FHIR R4 Document Bundle ready for Fidelius encryption and push to HIU.
 */
class FhirBundleBuilder
{
    /**
     * Build an NDHM FHIR R4 Prescription Document Bundle from database records.
     *
     * @param array $rx Prescription record row from prescriptions table
     * @param array $patient Patient row from users table
     * @param array $doctor Doctor row from doctors table
     * @param array $org Facility details (name, hfrId, etc.)
     * @return array FHIR R4 Bundle structure
     */
    public static function buildPrescriptionBundle(array $rx, array $patient, array $doctor, array $org = []): array
    {
        $bundleId = self::uuid();
        $compId = self::uuid();
        $patientId = 'pat-' . ($patient['id'] ?? '1');
        $practitionerId = 'prac-' . ($doctor['id'] ?? '1');
        $orgId = 'org-' . ($org['hfr_id'] ?? 'rejuvenate');
        $nowIso = gmdate('Y-m-d\TH:i:s.000\Z');

        $orgName = $org['name'] ?? defined('ABDM_HIP_NAME') ? ABDM_HIP_NAME : 'Rejuvenate Digital Health';
        $hfrFacilityId = $org['hfr_id'] ?? defined('ABDM_HFR_FACILITY_ID') ? ABDM_HFR_FACILITY_ID : 'IN0810000001';

        $entries = [];
        $sectionEntries = [];

        // 1. Patient Resource
        $patientResource = [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Patient']
            ],
            'identifier' => [],
            'name' => [
                [
                    'use' => 'official',
                    'text' => $patient['name'] ?? 'Patient'
                ]
            ],
            'gender' => self::mapGender($patient['gender'] ?? ''),
        ];

        if (!empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
            $patientResource['birthDate'] = date('Y-m-d', strtotime($patient['dob']));
        }

        if (!empty($patient['phone'])) {
            $patientResource['telecom'][] = [
                'system' => 'phone',
                'value' => '+91' . preg_replace('/\D/', '', $patient['phone'])
            ];
        }

        if (!empty($patient['email'])) {
            $patientResource['telecom'][] = [
                'system' => 'email',
                'value' => $patient['email']
            ];
        }

        if (!empty($rx['abha_number']) || !empty($patient['abha_id'])) {
            $patientResource['identifier'][] = [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-identifier-type-code',
                            'code' => 'ABHA',
                            'display' => 'Ayushman Bharat Health Account'
                        ]
                    ]
                ],
                'system' => 'https://healthid.ndhm.gov.in',
                'value' => $rx['abha_number'] ?: $patient['abha_id']
            ];
        }

        if (!empty($patient['abha_address'])) {
            $patientResource['identifier'][] = [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-identifier-type-code',
                            'code' => 'ABHA-Address',
                            'display' => 'ABHA Address'
                        ]
                    ]
                ],
                'system' => 'https://phr.ndhm.gov.in',
                'value' => $patient['abha_address']
            ];
        }

        // 2. Practitioner Resource
        $practitionerResource = [
            'resourceType' => 'Practitioner',
            'id' => $practitionerId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Practitioner']
            ],
            'identifier' => [
                [
                    'system' => 'https://doctor.ndhm.gov.in',
                    'value' => $rx['hpr_id'] ?: ($doctor['hpr_id'] ?? 'HPR-' . ($doctor['id'] ?? '1'))
                ]
            ],
            'name' => [
                [
                    'prefix' => ['Dr.'],
                    'text' => $doctor['name'] ?? 'Doctor'
                ]
            ]
        ];

        if (!empty($doctor['degrees'])) {
            $practitionerResource['qualification'] = [
                [
                    'code' => [
                        'text' => $doctor['degrees']
                    ]
                ]
            ];
        }

        // 3. Organization Resource
        $organizationResource = [
            'resourceType' => 'Organization',
            'id' => $orgId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Organization']
            ],
            'identifier' => [
                [
                    'system' => 'https://facility.ndhm.gov.in',
                    'value' => $hfrFacilityId
                ]
            ],
            'name' => $orgName
        ];

        // 4. Condition Resources (Diagnosis & ICD)
        if (!empty($rx['diagnosis'])) {
            $condId = 'cond-' . self::uuid();
            $condResource = [
                'resourceType' => 'Condition',
                'id' => $condId,
                'meta' => [
                    'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Condition']
                ],
                'clinicalStatus' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                            'code' => 'active',
                            'display' => 'Active'
                        ]
                    ]
                ],
                'code' => [
                    'text' => $rx['diagnosis']
                ],
                'subject' => [
                    'reference' => 'Patient/' . $patientId,
                    'display' => $patient['name'] ?? 'Patient'
                ],
                'recordedDate' => date('Y-m-d', strtotime($rx['visit_date'] ?? 'now'))
            ];

            if (!empty($rx['icd_codes'])) {
                $condResource['code']['coding'] = [
                    [
                        'system' => 'http://hl7.org/fhir/sid/icd-10',
                        'code' => trim($rx['icd_codes']),
                        'display' => $rx['diagnosis']
                    ]
                ];
            }

            $entries[] = ['fullUrl' => 'Condition/' . $condId, 'resource' => $condResource];
            $sectionEntries[] = ['reference' => 'Condition/' . $condId];
        }

        // 5. MedicationRequest Resources
        $medications = is_string($rx['medications'] ?? null) ? json_decode($rx['medications'], true) : ($rx['medications'] ?? []);
        if (is_array($medications)) {
            foreach ($medications as $idx => $med) {
                $medName = trim($med['name'] ?? $med['medicine'] ?? '');
                if ($medName === '') continue;

                $medReqId = 'med-' . self::uuid();
                $dosageText = trim(($med['dose'] ?? '') . ' ' . ($med['frequency'] ?? '') . ' ' . ($med['duration'] ?? ''));
                if (!empty($med['instructions'])) {
                    $dosageText .= ' (' . $med['instructions'] . ')';
                }

                $medResource = [
                    'resourceType' => 'MedicationRequest',
                    'id' => $medReqId,
                    'meta' => [
                        'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/MedicationRequest']
                    ],
                    'status' => 'active',
                    'intent' => 'order',
                    'medicationCodeableConcept' => [
                        'text' => $medName
                    ],
                    'subject' => [
                        'reference' => 'Patient/' . $patientId,
                        'display' => $patient['name'] ?? 'Patient'
                    ],
                    'authoredOn' => date('Y-m-d', strtotime($rx['visit_date'] ?? 'now')),
                    'requester' => [
                        'reference' => 'Practitioner/' . $practitionerId,
                        'display' => 'Dr. ' . ($doctor['name'] ?? 'Doctor')
                    ],
                    'dosageInstruction' => [
                        [
                            'text' => $dosageText ?: 'As directed by physician',
                            'route' => [
                                'text' => $med['route'] ?? 'Oral'
                            ]
                        ]
                    ]
                ];

                $entries[] = ['fullUrl' => 'MedicationRequest/' . $medReqId, 'resource' => $medResource];
                $sectionEntries[] = ['reference' => 'MedicationRequest/' . $medReqId];
            }
        }

        // 6. Observation Resources (Vitals)
        $vitals = is_string($rx['vitals'] ?? null) ? json_decode($rx['vitals'], true) : ($rx['vitals'] ?? []);
        if (is_array($vitals)) {
            // Blood Pressure
            if (!empty($vitals['bp_sys']) && !empty($vitals['bp_dia'])) {
                $bpId = 'obs-' . self::uuid();
                $bpResource = [
                    'resourceType' => 'Observation',
                    'id' => $bpId,
                    'meta' => [
                        'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Observation']
                    ],
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => '85354-9',
                                'display' => 'Blood pressure panel'
                            ]
                        ],
                        'text' => 'Blood Pressure'
                    ],
                    'subject' => ['reference' => 'Patient/' . $patientId],
                    'component' => [
                        [
                            'code' => [
                                'coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']],
                                'text' => 'Systolic'
                            ],
                            'valueQuantity' => ['value' => (float)$vitals['bp_sys'], 'unit' => 'mmHg', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']
                        ],
                        [
                            'code' => [
                                'coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']],
                                'text' => 'Diastolic'
                            ],
                            'valueQuantity' => ['value' => (float)$vitals['bp_dia'], 'unit' => 'mmHg', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']
                        ]
                    ]
                ];
                $entries[] = ['fullUrl' => 'Observation/' . $bpId, 'resource' => $bpResource];
                $sectionEntries[] = ['reference' => 'Observation/' . $bpId];
            }

            // Pulse
            if (!empty($vitals['pulse'])) {
                $pId = 'obs-' . self::uuid();
                $pResource = [
                    'resourceType' => 'Observation',
                    'id' => $pId,
                    'status' => 'final',
                    'code' => [
                        'coding' => [['system' => 'http://loinc.org', 'code' => '8867-4', 'display' => 'Heart rate']],
                        'text' => 'Pulse rate'
                    ],
                    'subject' => ['reference' => 'Patient/' . $patientId],
                    'valueQuantity' => ['value' => (float)$vitals['pulse'], 'unit' => 'beats/minute', 'system' => 'http://unitsofmeasure.org', 'code' => '/min']
                ];
                $entries[] = ['fullUrl' => 'Observation/' . $pId, 'resource' => $pResource];
                $sectionEntries[] = ['reference' => 'Observation/' . $pId];
            }

            // SpO2
            if (!empty($vitals['spo2'])) {
                $sId = 'obs-' . self::uuid();
                $sResource = [
                    'resourceType' => 'Observation',
                    'id' => $sId,
                    'status' => 'final',
                    'code' => [
                        'coding' => [['system' => 'http://loinc.org', 'code' => '2708-6', 'display' => 'Oxygen saturation in Arterial blood by Pulse oximetry']],
                        'text' => 'SpO2'
                    ],
                    'subject' => ['reference' => 'Patient/' . $patientId],
                    'valueQuantity' => ['value' => (float)$vitals['spo2'], 'unit' => '%', 'system' => 'http://unitsofmeasure.org', 'code' => '%']
                ];
                $entries[] = ['fullUrl' => 'Observation/' . $sId, 'resource' => $sResource];
                $sectionEntries[] = ['reference' => 'Observation/' . $sId];
            }
        }

        // 7. Composition Resource (Document Entry #1 per FHIR Document specifications)
        $compositionResource = [
            'resourceType' => 'Composition',
            'id' => $compId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/PrescriptionRecord']
            ],
            'status' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '440545006',
                        'display' => 'Prescription record'
                    ]
                ],
                'text' => 'Prescription record'
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientId,
                'display' => $patient['name'] ?? 'Patient'
            ],
            'date' => date('Y-m-d\TH:i:s.000\Z', strtotime($rx['visit_date'] ?? 'now')),
            'author' => [
                [
                    'reference' => 'Practitioner/' . $practitionerId,
                    'display' => 'Dr. ' . ($doctor['name'] ?? 'Doctor')
                ]
            ],
            'title' => 'Consultation Prescription Note',
            'custodian' => [
                'reference' => 'Organization/' . $orgId,
                'display' => $orgName
            ],
            'section' => [
                [
                    'title' => 'OPD Prescription Record',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://snomed.info/sct',
                                'code' => '440545006',
                                'display' => 'Prescription record'
                            ]
                        ]
                    ],
                    'entry' => $sectionEntries
                ]
            ]
        ];

        // Bundle Assembly: Composition is ALWAYS first entry
        $finalEntries = [
            ['fullUrl' => 'Composition/' . $compId, 'resource' => $compositionResource],
            ['fullUrl' => 'Patient/' . $patientId, 'resource' => $patientResource],
            ['fullUrl' => 'Practitioner/' . $practitionerId, 'resource' => $practitionerResource],
            ['fullUrl' => 'Organization/' . $orgId, 'resource' => $organizationResource],
        ];

        foreach ($entries as $e) {
            $finalEntries[] = $e;
        }

        return [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $nowIso,
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle']
            ],
            'identifier' => [
                'system' => 'https://rejuvenatedigitalhealth.com/bundle',
                'value' => $bundleId
            ],
            'type' => 'document',
            'timestamp' => $nowIso,
            'entry' => $finalEntries
        ];
    }

    /**
     * Build an NDHM FHIR R4 OPConsultRecord Bundle from the same visit's prescription row
     * (chief_complaints / examination / diagnosis / advice — the consult note itself).
     *
     * @param array $rx Prescription record row from prescriptions table (same row buildPrescriptionBundle uses)
     * @param array $patient Patient row from users table
     * @param array $doctor Doctor row from doctors table
     * @param array $org Facility details (name, hfrId, etc.)
     * @return array FHIR R4 Bundle structure
     */
    public static function buildOpConsultBundle(array $rx, array $patient, array $doctor, array $org = []): array
    {
        $bundleId = self::uuid();
        $compId = self::uuid();
        $encounterId = 'enc-' . self::uuid();
        $patientId = 'pat-' . ($patient['id'] ?? '1');
        $practitionerId = 'prac-' . ($doctor['id'] ?? '1');
        $orgId = 'org-' . ($org['hfr_id'] ?? 'rejuvenate');
        $nowIso = gmdate('Y-m-d\TH:i:s.000\Z');
        $visitIso = date('Y-m-d\TH:i:s.000\Z', strtotime($rx['visit_date'] ?? 'now'));

        $orgName = $org['name'] ?? defined('ABDM_HIP_NAME') ? ABDM_HIP_NAME : 'Rejuvenate Digital Health';
        $hfrFacilityId = $org['hfr_id'] ?? defined('ABDM_HFR_FACILITY_ID') ? ABDM_HFR_FACILITY_ID : 'IN0810000001';

        $entries = [];
        $sectionEntries = [];

        // Patient Resource — same shape as buildPrescriptionBundle().
        $patientResource = [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Patient']],
            'identifier' => [],
            'name' => [['use' => 'official', 'text' => $patient['name'] ?? 'Patient']],
            'gender' => self::mapGender($patient['gender'] ?? ''),
        ];
        if (!empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
            $patientResource['birthDate'] = date('Y-m-d', strtotime($patient['dob']));
        }
        if (!empty($rx['abha_number']) || !empty($patient['abha_id'])) {
            $patientResource['identifier'][] = [
                'type' => ['coding' => [['system' => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-identifier-type-code', 'code' => 'ABHA', 'display' => 'Ayushman Bharat Health Account']]],
                'system' => 'https://healthid.ndhm.gov.in',
                'value' => $rx['abha_number'] ?: $patient['abha_id']
            ];
        }
        if (!empty($patient['abha_address'])) {
            $patientResource['identifier'][] = [
                'type' => ['coding' => [['system' => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-identifier-type-code', 'code' => 'ABHA-Address', 'display' => 'ABHA Address']]],
                'system' => 'https://phr.ndhm.gov.in',
                'value' => $patient['abha_address']
            ];
        }

        // Practitioner Resource
        $practitionerResource = [
            'resourceType' => 'Practitioner',
            'id' => $practitionerId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Practitioner']],
            'identifier' => [[
                'system' => 'https://doctor.ndhm.gov.in',
                'value' => $rx['hpr_id'] ?: ($doctor['hpr_id'] ?? 'HPR-' . ($doctor['id'] ?? '1'))
            ]],
            'name' => [['prefix' => ['Dr.'], 'text' => $doctor['name'] ?? 'Doctor']]
        ];

        // Organization Resource
        $organizationResource = [
            'resourceType' => 'Organization',
            'id' => $orgId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Organization']],
            'identifier' => [['system' => 'https://facility.ndhm.gov.in', 'value' => $hfrFacilityId]],
            'name' => $orgName
        ];

        // Encounter Resource — the OPD visit itself
        $encounterResource = [
            'resourceType' => 'Encounter',
            'id' => $encounterId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Encounter']],
            'status' => 'finished',
            'class' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => 'AMB', 'display' => 'ambulatory']]],
            'subject' => ['reference' => 'Patient/' . $patientId, 'display' => $patient['name'] ?? 'Patient'],
            'participant' => [[
                'individual' => ['reference' => 'Practitioner/' . $practitionerId, 'display' => 'Dr. ' . ($doctor['name'] ?? 'Doctor')]
            ]],
            'period' => ['start' => $visitIso],
        ];
        $entries[] = ['fullUrl' => 'Encounter/' . $encounterId, 'resource' => $encounterResource];
        $sectionEntries['encounter'][] = ['reference' => 'Encounter/' . $encounterId];

        // Chief Complaints → Condition (reason for visit)
        if (!empty($rx['chief_complaints'])) {
            $ccId = 'cond-' . self::uuid();
            $entries[] = ['fullUrl' => 'Condition/' . $ccId, 'resource' => [
                'resourceType' => 'Condition',
                'id' => $ccId,
                'clinicalStatus' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical', 'code' => 'active', 'display' => 'Active']]],
                'code' => ['text' => $rx['chief_complaints']],
                'subject' => ['reference' => 'Patient/' . $patientId],
                'recordedDate' => date('Y-m-d', strtotime($rx['visit_date'] ?? 'now')),
            ]];
            $sectionEntries['chief_complaints'][] = ['reference' => 'Condition/' . $ccId];
        }

        // Diagnosis → Condition (same shape as buildPrescriptionBundle's diagnosis block)
        if (!empty($rx['diagnosis'])) {
            $diagId = 'cond-' . self::uuid();
            $diagCode = ['text' => $rx['diagnosis']];
            if (!empty($rx['icd_codes'])) {
                $diagCode['coding'] = [['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => trim($rx['icd_codes']), 'display' => $rx['diagnosis']]];
            }
            $entries[] = ['fullUrl' => 'Condition/' . $diagId, 'resource' => [
                'resourceType' => 'Condition',
                'id' => $diagId,
                'clinicalStatus' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical', 'code' => 'active', 'display' => 'Active']]],
                'code' => $diagCode,
                'subject' => ['reference' => 'Patient/' . $patientId],
                'recordedDate' => date('Y-m-d', strtotime($rx['visit_date'] ?? 'now')),
            ]];
            $sectionEntries['diagnosis'][] = ['reference' => 'Condition/' . $diagId];
        }

        // Examination findings + advice → free-text Observation entries (same text-only
        // approach used for Phase 1/2 — no attempt to code these into LOINC yet).
        if (!empty($rx['examination'])) {
            $exId = 'obs-' . self::uuid();
            $entries[] = ['fullUrl' => 'Observation/' . $exId, 'resource' => [
                'resourceType' => 'Observation',
                'id' => $exId,
                'status' => 'final',
                'code' => ['text' => 'Examination findings'],
                'subject' => ['reference' => 'Patient/' . $patientId],
                'valueString' => $rx['examination'],
            ]];
            $sectionEntries['examination'][] = ['reference' => 'Observation/' . $exId];
        }

        if (!empty($rx['advice'])) {
            $advId = 'proc-' . self::uuid();
            $entries[] = ['fullUrl' => 'ServiceRequest/' . $advId, 'resource' => [
                'resourceType' => 'ServiceRequest',
                'id' => $advId,
                'status' => 'active',
                'intent' => 'plan',
                'code' => ['text' => 'Advice / follow-up plan'],
                'subject' => ['reference' => 'Patient/' . $patientId],
                'patientInstruction' => $rx['advice'],
            ]];
            $sectionEntries['advice'][] = ['reference' => 'ServiceRequest/' . $advId];
        }

        // Composition Resource — one section per note-type captured above.
        $sections = [];
        $sectionDefs = [
            'chief_complaints' => ['title' => 'Chief Complaints', 'code' => '422843007', 'display' => 'Chief complaint section'],
            'examination'      => ['title' => 'Examination', 'code' => '425044008', 'display' => 'Physical exam section'],
            'diagnosis'        => ['title' => 'Diagnosis', 'code' => '51847-2', 'display' => 'Diagnosis'],
            'advice'           => ['title' => 'Advice / Follow-up', 'code' => '18776-5', 'display' => 'Plan of care note'],
        ];
        foreach ($sectionDefs as $key => $def) {
            if (empty($sectionEntries[$key])) continue;
            $sections[] = [
                'title' => $def['title'],
                'code' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $def['code'], 'display' => $def['display']]]],
                'entry' => $sectionEntries[$key],
            ];
        }

        $compositionResource = [
            'resourceType' => 'Composition',
            'id' => $compId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/OPConsultRecord']],
            'status' => 'final',
            'type' => [
                'coding' => [['system' => 'http://snomed.info/sct', 'code' => '371530004', 'display' => 'Clinical consultation report']],
                'text' => 'Consultation report'
            ],
            'subject' => ['reference' => 'Patient/' . $patientId, 'display' => $patient['name'] ?? 'Patient'],
            'encounter' => ['reference' => 'Encounter/' . $encounterId],
            'date' => $visitIso,
            'author' => [['reference' => 'Practitioner/' . $practitionerId, 'display' => 'Dr. ' . ($doctor['name'] ?? 'Doctor')]],
            'title' => 'OPD Consultation Note',
            'custodian' => ['reference' => 'Organization/' . $orgId, 'display' => $orgName],
            'section' => $sections ?: [[
                'title' => 'Consultation',
                'code' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => '371530004', 'display' => 'Clinical consultation report']]],
                'entry' => [['reference' => 'Encounter/' . $encounterId]],
            ]],
        ];

        $finalEntries = [
            ['fullUrl' => 'Composition/' . $compId, 'resource' => $compositionResource],
            ['fullUrl' => 'Patient/' . $patientId, 'resource' => $patientResource],
            ['fullUrl' => 'Practitioner/' . $practitionerId, 'resource' => $practitionerResource],
            ['fullUrl' => 'Organization/' . $orgId, 'resource' => $organizationResource],
        ];
        foreach ($entries as $e) {
            $finalEntries[] = $e;
        }

        return [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $nowIso,
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle']
            ],
            'identifier' => ['system' => 'https://rejuvenatedigitalhealth.com/bundle', 'value' => $bundleId],
            'type' => 'document',
            'timestamp' => $nowIso,
            'entry' => $finalEntries
        ];
    }

    /**
     * Build an NDHM FHIR R4 HealthDocumentRecord Bundle from an uploaded patient document.
     *
     * @param array $doc Row from patient_documents (document_name, document_type, file_path, file_type, uploaded_at)
     * @param array $patient Patient row from users table
     * @param array $org Facility details (name, hfrId, etc.)
     * @return array FHIR R4 Bundle structure
     */
    public static function buildHealthDocumentBundle(array $doc, array $patient, array $org = []): array
    {
        $bundleId = self::uuid();
        $compId = self::uuid();
        $docRefId = 'docref-' . self::uuid();
        $binaryId = 'bin-' . self::uuid();
        $patientId = 'pat-' . ($patient['id'] ?? '1');
        $orgId = 'org-' . ($org['hfr_id'] ?? 'rejuvenate');
        $nowIso = gmdate('Y-m-d\TH:i:s.000\Z');

        $orgName = $org['name'] ?? defined('ABDM_HIP_NAME') ? ABDM_HIP_NAME : 'Rejuvenate Digital Health';
        $hfrFacilityId = $org['hfr_id'] ?? defined('ABDM_HFR_FACILITY_ID') ? ABDM_HFR_FACILITY_ID : 'IN0810000001';

        // Patient Resource — same shape as buildPrescriptionBundle().
        $patientResource = [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Patient']
            ],
            'identifier' => [],
            'name' => [
                [
                    'use' => 'official',
                    'text' => $patient['name'] ?? 'Patient'
                ]
            ],
            'gender' => self::mapGender($patient['gender'] ?? ''),
        ];

        if (!empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
            $patientResource['birthDate'] = date('Y-m-d', strtotime($patient['dob']));
        }

        if (!empty($patient['abha_id'])) {
            $patientResource['identifier'][] = [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-identifier-type-code',
                            'code' => 'ABHA',
                            'display' => 'Ayushman Bharat Health Account'
                        ]
                    ]
                ],
                'system' => 'https://healthid.ndhm.gov.in',
                'value' => $patient['abha_id']
            ];
        }

        if (!empty($patient['abha_address'])) {
            $patientResource['identifier'][] = [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-identifier-type-code',
                            'code' => 'ABHA-Address',
                            'display' => 'ABHA Address'
                        ]
                    ]
                ],
                'system' => 'https://phr.ndhm.gov.in',
                'value' => $patient['abha_address']
            ];
        }

        // Organization Resource
        $organizationResource = [
            'resourceType' => 'Organization',
            'id' => $orgId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Organization']
            ],
            'identifier' => [
                [
                    'system' => 'https://facility.ndhm.gov.in',
                    'value' => $hfrFacilityId
                ]
            ],
            'name' => $orgName
        ];

        // DocumentReference + Binary — the actual uploaded file content, base64-encoded.
        // The file is read from disk here (not stored pre-encoded) so a missing/unreadable
        // file degrades to a DocumentReference with no attachment data instead of failing
        // the whole push.
        $contentType = $doc['file_type'] ?: 'application/octet-stream';
        $docName = trim((string) ($doc['document_name'] ?? '')) ?: 'Health Document';
        $docTypeText = trim((string) ($doc['document_type'] ?? '')) ?: 'Clinical Document';
        $fileBytes = self::readDocumentFile((string) ($doc['file_path'] ?? ''));

        $attachment = [
            'contentType' => $contentType,
            'url' => 'Binary/' . $binaryId,
            'title' => $docName,
        ];

        $docRefResource = [
            'resourceType' => 'DocumentReference',
            'id' => $docRefId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentReference']
            ],
            'status' => 'current',
            'type' => ['text' => $docTypeText],
            'subject' => [
                'reference' => 'Patient/' . $patientId,
                'display' => $patient['name'] ?? 'Patient'
            ],
            'date' => date('Y-m-d\TH:i:s.000\Z', strtotime($doc['uploaded_at'] ?? 'now')),
            'description' => $docName,
            'content' => [
                ['attachment' => $attachment]
            ]
        ];

        $binaryResource = [
            'resourceType' => 'Binary',
            'id' => $binaryId,
            'contentType' => $contentType,
        ];
        if ($fileBytes !== null) {
            $binaryResource['data'] = base64_encode($fileBytes);
        }

        // Composition Resource (Document Entry #1 per FHIR Document specifications)
        $compositionResource = [
            'resourceType' => 'Composition',
            'id' => $compId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/HealthDocumentRecord']
            ],
            'status' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '419891008',
                        'display' => 'Record artifact'
                    ]
                ],
                'text' => 'Health document record'
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientId,
                'display' => $patient['name'] ?? 'Patient'
            ],
            'date' => date('Y-m-d\TH:i:s.000\Z', strtotime($doc['uploaded_at'] ?? 'now')),
            'author' => [
                [
                    'reference' => 'Organization/' . $orgId,
                    'display' => $orgName
                ]
            ],
            'title' => $docName,
            'custodian' => [
                'reference' => 'Organization/' . $orgId,
                'display' => $orgName
            ],
            'section' => [
                [
                    'title' => 'Health Document',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://snomed.info/sct',
                                'code' => '419891008',
                                'display' => 'Record artifact'
                            ]
                        ]
                    ],
                    'entry' => [
                        ['reference' => 'DocumentReference/' . $docRefId]
                    ]
                ]
            ]
        ];

        // Bundle Assembly: Composition is ALWAYS first entry
        $finalEntries = [
            ['fullUrl' => 'Composition/' . $compId, 'resource' => $compositionResource],
            ['fullUrl' => 'Patient/' . $patientId, 'resource' => $patientResource],
            ['fullUrl' => 'Organization/' . $orgId, 'resource' => $organizationResource],
            ['fullUrl' => 'DocumentReference/' . $docRefId, 'resource' => $docRefResource],
            ['fullUrl' => 'Binary/' . $binaryId, 'resource' => $binaryResource],
        ];

        return [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $nowIso,
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle']
            ],
            'identifier' => [
                'system' => 'https://rejuvenatedigitalhealth.com/bundle',
                'value' => $bundleId
            ],
            'type' => 'document',
            'timestamp' => $nowIso,
            'entry' => $finalEntries
        ];
    }

    /**
     * Build an NDHM FHIR R4 DiagnosticReportRecord Bundle from a lab booking's report.
     *
     * @param array $booking Row from lab_bookings (tests_json, report_file, booking_date, status)
     * @param array $patient Patient row from users table
     * @param array $org Facility details (name, hfrId, etc.)
     * @return array FHIR R4 Bundle structure
     */
    public static function buildDiagnosticReportBundle(array $booking, array $patient, array $org = []): array
    {
        $bundleId = self::uuid();
        $compId = self::uuid();
        $reportId = 'diagrep-' . self::uuid();
        $binaryId = 'bin-' . self::uuid();
        $patientId = 'pat-' . ($patient['id'] ?? '1');
        $orgId = 'org-' . ($org['hfr_id'] ?? 'rejuvenate');
        $nowIso = gmdate('Y-m-d\TH:i:s.000\Z');

        $orgName = $org['name'] ?? defined('ABDM_HIP_NAME') ? ABDM_HIP_NAME : 'Rejuvenate Digital Health';
        $hfrFacilityId = $org['hfr_id'] ?? defined('ABDM_HFR_FACILITY_ID') ? ABDM_HFR_FACILITY_ID : 'IN0810000001';

        // Patient Resource — same shape as buildPrescriptionBundle().
        $patientResource = [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Patient']
            ],
            'identifier' => [],
            'name' => [
                [
                    'use' => 'official',
                    'text' => $patient['name'] ?? 'Patient'
                ]
            ],
            'gender' => self::mapGender($patient['gender'] ?? ''),
        ];

        if (!empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
            $patientResource['birthDate'] = date('Y-m-d', strtotime($patient['dob']));
        }

        if (!empty($patient['abha_id'])) {
            $patientResource['identifier'][] = [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-identifier-type-code',
                            'code' => 'ABHA',
                            'display' => 'Ayushman Bharat Health Account'
                        ]
                    ]
                ],
                'system' => 'https://healthid.ndhm.gov.in',
                'value' => $patient['abha_id']
            ];
        }

        if (!empty($patient['abha_address'])) {
            $patientResource['identifier'][] = [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-identifier-type-code',
                            'code' => 'ABHA-Address',
                            'display' => 'ABHA Address'
                        ]
                    ]
                ],
                'system' => 'https://phr.ndhm.gov.in',
                'value' => $patient['abha_address']
            ];
        }

        // Organization Resource
        $organizationResource = [
            'resourceType' => 'Organization',
            'id' => $orgId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Organization']
            ],
            'identifier' => [
                [
                    'system' => 'https://facility.ndhm.gov.in',
                    'value' => $hfrFacilityId
                ]
            ],
            'name' => $orgName
        ];

        // Test names from tests_json, used as the DiagnosticReport's code/display text.
        $tests = is_string($booking['tests_json'] ?? null) ? json_decode($booking['tests_json'], true) : ($booking['tests_json'] ?? []);
        $testNames = [];
        if (is_array($tests)) {
            foreach ($tests as $t) {
                $n = is_array($t) ? trim((string) ($t['name'] ?? $t['test_name'] ?? '')) : trim((string) $t);
                if ($n !== '') $testNames[] = $n;
            }
        }
        $reportTitle = $testNames ? implode(', ', $testNames) : 'Diagnostic Lab Report';
        $reportDate = date('Y-m-d\TH:i:s.000\Z', strtotime($booking['booking_date'] ?? 'now'));

        // DocumentReference-style attachment of the actual uploaded report file (PDF/image).
        $contentType = self::guessMimeFromExtension((string) ($booking['report_file'] ?? ''));
        $fileBytes = self::readDocumentFile((string) ($booking['report_file'] ?? ''));

        $presentedForm = [
            'contentType' => $contentType,
            'url' => 'Binary/' . $binaryId,
            'title' => $reportTitle,
        ];

        $diagnosticReportResource = [
            'resourceType' => 'DiagnosticReport',
            'id' => $reportId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DiagnosticReport']
            ],
            'status' => 'final',
            'code' => [
                'text' => $reportTitle
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientId,
                'display' => $patient['name'] ?? 'Patient'
            ],
            'effectiveDateTime' => $reportDate,
            'issued' => $reportDate,
            'performer' => [
                [
                    'reference' => 'Organization/' . $orgId,
                    'display' => $orgName
                ]
            ],
            'presentedForm' => [$presentedForm]
        ];

        $binaryResource = [
            'resourceType' => 'Binary',
            'id' => $binaryId,
            'contentType' => $contentType,
        ];
        if ($fileBytes !== null) {
            $binaryResource['data'] = base64_encode($fileBytes);
        }

        // Composition Resource (Document Entry #1 per FHIR Document specifications)
        $compositionResource = [
            'resourceType' => 'Composition',
            'id' => $compId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DiagnosticReportRecord']
            ],
            'status' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '721981007',
                        'display' => 'Diagnostic studies report'
                    ]
                ],
                'text' => 'Diagnostic report record'
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientId,
                'display' => $patient['name'] ?? 'Patient'
            ],
            'date' => $reportDate,
            'author' => [
                [
                    'reference' => 'Organization/' . $orgId,
                    'display' => $orgName
                ]
            ],
            'title' => $reportTitle,
            'custodian' => [
                'reference' => 'Organization/' . $orgId,
                'display' => $orgName
            ],
            'section' => [
                [
                    'title' => 'Diagnostic Report',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://snomed.info/sct',
                                'code' => '721981007',
                                'display' => 'Diagnostic studies report'
                            ]
                        ]
                    ],
                    'entry' => [
                        ['reference' => 'DiagnosticReport/' . $reportId]
                    ]
                ]
            ]
        ];

        // Bundle Assembly: Composition is ALWAYS first entry
        $finalEntries = [
            ['fullUrl' => 'Composition/' . $compId, 'resource' => $compositionResource],
            ['fullUrl' => 'Patient/' . $patientId, 'resource' => $patientResource],
            ['fullUrl' => 'Organization/' . $orgId, 'resource' => $organizationResource],
            ['fullUrl' => 'DiagnosticReport/' . $reportId, 'resource' => $diagnosticReportResource],
            ['fullUrl' => 'Binary/' . $binaryId, 'resource' => $binaryResource],
        ];

        return [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $nowIso,
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle']
            ],
            'identifier' => [
                'system' => 'https://rejuvenatedigitalhealth.com/bundle',
                'value' => $bundleId
            ],
            'type' => 'document',
            'timestamp' => $nowIso,
            'entry' => $finalEntries
        ];
    }

    private static function guessMimeFromExtension(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }

    /**
     * Resolve and read an uploaded document's bytes for FHIR Binary encoding.
     *
     * patient_documents.file_path has been saved inconsistently over time (some rows
     * carry a '../uploads/...' prefix, newer rows just 'uploads/...'), so both forms are
     * normalised to a path under the project's own uploads/ directory. The resolved real
     * path is then required to stay inside that directory — never trust a stored path
     * enough to let it read anything outside uploads/ (defends against a path-traversal
     * value ever having been written into the column).
     */
    private static function readDocumentFile(string $relativePath): ?string
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $relativePath = preg_replace('#^(\.\./)+#', '', $relativePath);
        $uploadsRoot = realpath(__DIR__ . '/../uploads');
        if ($uploadsRoot === false) {
            return null;
        }

        $full = __DIR__ . '/../' . $relativePath;
        $realFull = realpath($full);
        if ($realFull === false || strpos($realFull, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        $bytes = @file_get_contents($realFull);
        return $bytes === false ? null : $bytes;
    }

    private static function mapGender(string $g): string
    {
        $g = strtolower(trim($g));
        if ($g === 'male' || $g === 'm') return 'male';
        if ($g === 'female' || $g === 'f') return 'female';
        return 'other';
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
