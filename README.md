# fhr
Lightweight FHIR server based on MySQL-JSON/PHP

Uses MySQL JSON capabilities to mimic a FHIR (HL7) server. Does not include FHIR entities, works purely on JSON SQL processing with a FHIR like Rest API syntax.
Can take any FHIRish JSON resources, creates the necessary tables on first encounter.

