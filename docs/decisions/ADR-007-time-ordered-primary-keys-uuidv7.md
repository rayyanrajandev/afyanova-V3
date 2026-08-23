# ADR-007: Time-Ordered Primary Keys (UUIDv7 / ULID)

## Status
**Accepted**

## Context
Standard auto-incrementing integer IDs (`BIGINT AUTO_INCREMENT`) create security risks in healthcare web applications by allowing external attackers or malicious users to enumerate patient counts, encounter numbers, and invoice volumes (IDOR vulnerability). However, random UUIDv4 identifiers cause severe B-Tree index fragmentation and poor insertion throughput in high-volume PostgreSQL databases.

## Decision
AfyaNova V3 adopts **UUIDv7** (RFC 9562) and **ULID** as the universal primary key format across all tables.

### Rationale:
1. **Time-Ordered**: The first 48 bits encode a millisecond-precision timestamp, ensuring sequential B-Tree index insertion locality and high write throughput.
2. **Cryptographic Randomness**: 74 bits of entropy prevent predictable enumeration and guessing of patient or clinical records.
3. **Client-Side / Offline Generation**: Unique identifiers can be generated reliably in background jobs or edge devices without central database round-trips.

## Consequences
### Positive:
- Total protection against ID enumeration attacks.
- High B-tree index insertion performance comparable to auto-incrementing integers.
- Distributed ID generation capability.

### Negative:
- Column storage requires 16 bytes per UUID vs 8 bytes for `BIGINT`.
