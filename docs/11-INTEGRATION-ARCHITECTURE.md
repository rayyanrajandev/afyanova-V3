# AfyaNova V3 — Integration & Interoperability Architecture

## 1. Integration Architecture & Resilience

External system integrations in healthcare must be fault-tolerant, idempotent, and secure. AfyaNova V3 isolates all external communication behind dedicated **Integration Adapters** and executes them via asynchronous, resilient queues.

```
┌────────────────────────────────────────────────────────┐
│                   AFYANOVA APPLICATION                │
└───────────────────────────┬────────────────────────────┘
                            │ Dispatches Integration Job
┌───────────────────────────▼────────────────────────────┐
│                    REDIS QUEUE WORKER                  │
│    (Retry with Exponential Backoff, Dead Letter Queue) │
└───────────────────────────┬────────────────────────────┘
                            │
         ┌──────────────────┼──────────────────┐
         ▼                  ▼                  ▼
┌─────────────────┐┌─────────────────┐┌─────────────────┐
│  Mobile Money   ││  Lab Analyzers  ││  Gov & Registry │
│  (M-Pesa, etc.) ││  (ASTM / HL7)   ││  (DHIS2, HFR)   │
└─────────────────┘└─────────────────┘└─────────────────┘
```

---

## 2. Mobile Money & Payment Gateways (Tanzania)

Mobile money is the dominant payment method in private healthcare in Tanzania. AfyaNova V3 provides native drivers for:

1. **Vodacom M-Pesa (Daraja & C2B / Push)**:
   - **STK Push (USSD Prompt)**: Cashier enters patient phone number $\to$ Patient receives instant PIN prompt on handset $\to$ M-Pesa posts async callback to AfyaNova webhook $\to$ Payment verified and invoice settled in real-time.
   - **C2B Paybill / Lipa Kwa M-Pesa**: Direct payment matching using Patient MRN or Invoice Number as the reference.
2. **Airtel Money & Tigo Pesa (Mixx by Yas)**:
   - Automated push and callback reconciliation.
3. **Payment Aggregators (Selcom / AzamPay / DPO)**:
   - Unified checkout for combined Mobile Money + Visa/Mastercard processing.

### Idempotent Webhook Processing:
- Every inbound payment notification is verified against provider HMAC signatures.
- Webhook events are checked for idempotency using `provider_transaction_ref`. Duplicate callbacks are acknowledged with HTTP 200 without double-crediting the ledger.

---

## 3. Laboratory Analyzer Interfacing (ASTM / HL7)

Many hospital laboratories operate automated hematology and biochemistry analyzers (e.g. Mindray, Sysmex, Roche Cobas).

### The AfyaNova Edge Agent:
- A lightweight Rust/Go daemon deployed on the facility local network.
- Interfaces via RS-232 serial or TCP/IP socket with analyzers using standard **ASTM E1381/E1394** or **HL7 v2.5** protocols.
- **Bi-directional Flow**:
  1. *Query / Worklist*: Analyzer scans barcode on sample tube $\to$ Queries Edge Agent $\to$ Edge Agent fetches test profile from AfyaNova.
  2. *Result Ingestion*: Analyzer outputs raw results $\to$ Edge Agent parses parameters $\to$ Transmits structured JSON to AfyaNova API over TLS.

---

## 4. Radiology & PACS (DICOM Web)

AfyaNova V3 integrates with modern open-source and commercial PACS systems (e.g., Orthanc PACS, dcm4chee):
- **DICOM Modality Worklist (MWL)**: Automatically publishes scheduled radiology orders so imaging technicians can select the patient directly on the X-Ray, CT, or Ultrasound console.
- **DICOM Web Viewer (WADO-RS / OHIF Viewer)**: Clinicians can click "View Scan" directly from the patient consultation screen to open a zero-footprint web DICOM viewer embedded in the AfyaNova UI.

---

## 5. Government & Health Registry Integrations (Tanzania)

1. **HFR (Health Facility Registry)**: Facility verification and licensing sync.
2. **MoH HMIS / MTUHA / DHIS2 Reporting**:
   - Automated monthly export of aggregated outpatient morbidity (MTUHA Book 5), inpatient mortality, immunizations, and communicable disease surveillance directly into DHIS2 API format.

---

## 6. SMS & Patient Notifications

- Integrated with regional SMS gateways (**Beem Africa**, **NextSMS**) for:
  - Appointment confirmations and 24-hour reminders.
  - Electronic receipt links and M-Pesa payment confirmations.
  - Notification when laboratory results or prescription pickups are ready.
