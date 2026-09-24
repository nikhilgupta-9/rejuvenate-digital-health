<?php

/**
 * FhirBundleBuilder — Builds NDHM FHIR R4 Document Bundles for ABDM Health Information Exchange.
 *
 * Implements ABDM / NRCES FHIR R4 Profiles:
 *   - DocumentBundle: https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle
 *   - PrescriptionRecord: https://nrces.in/ndhm/fhir/r4/StructureDefinition/PrescriptionRecord
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
