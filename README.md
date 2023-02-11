# fhr
Lightweight FHIR server based on MySQL-JSON/PHP

Uses MySQL JSON capabilities to mimic a FHIR (HL7) server. Does not include FHIR entities, works purely on JSON SQL processing with a FHIR like Rest API syntax.
Resource can be checked against HL7 FHIR definition (http://www.hl7.org/fhir/).
Can take any FHIRish JSON resources, creates the necessary tables on first encounter.
Works well till a few million records.

