/**
 * Enterprise ID Generator — Hybrid HMAC Version
 * ==================================================================
 * Skema:
 *   TCK-[BRANCH]-[HASH12]
 *   INV-REG-[BRANCH]-[HASH12]
 *   INV-BLN-[BRANCH]-[HASH12]
 *   PAY-REG-[BRANCH]-[HASH12]
 *   PAY-BLN-[BRANCH]-[HASH12]
 *
 * Prinsip:
 * 1. HASH12 dihasilkan dari HMAC-SHA256(payload, SECRET) di-truncate 12 hex.
 *    -> Tidak bisa ditebak/dipalsukan tanpa tahu SECRET (beda dgn md5/sha1 biasa).
 * 2. Payload berisi field unik (cid, date, billId, subtype, nonce) supaya
 *    hasil hash-nya juga unik.
 * 3. Hash bersifat SATU ARAH — untuk "mengidentifikasi" record, hash ini
 *    disimpan sebagai kolom UNIQUE + INDEX di database (lookup by public_id),
 *    BUKAN di-decode balik.
 * 4. Kalau kebetulan collision (sangat jarang), sistem retry dengan
 *    menambah nonce lalu generate ulang.
 * ==================================================================
 */

import { randomBytes, createHmac } from "crypto";

export type Domain = "TCK" | "INV" | "PAY";
export type SubType = "REG" | "BLN"; // hanya dipakai untuk INV & PAY

export interface GenerateHashIdInput {
  domain: Domain;
  subType?: SubType;
  branch: string; // kode cabang 3 huruf, mis. "JTS"
  cid: string; // customer id / entity id sumber
  date: string; // tanggal/periode transaksi, format bebas asal konsisten, mis. "25-07-2026"
  refId: string; // nomor referensi internal (billId, ticketId, dst)
}

const HASH_LENGTH = 12; // 12 hex char = 48-bit space

/**
 * WAJIB disuplai dari environment variable, jangan hardcode di source code.
 * Rotasi secret ini butuh migrasi ulang seluruh ID lama kalau mau re-generate,
 * jadi simpan dengan aman (secret manager / vault), bukan di .env yang di-commit.
 */
function getSecret(): string {
  const secret = process.env.ID_HMAC_SECRET;
  if (!secret) {
    throw new Error(
      "ID_HMAC_SECRET belum di-set di environment. Wajib ada sebelum generate ID."
    );
  }
  return secret;
}

/**
 * Hitung HMAC-SHA256 lalu ambil 12 karakter hex pertama (uppercase).
 * `nonce` opsional dipakai untuk retry kalau terjadi collision.
 */
function computeHash(payload: string, nonce = 0): string {
  const secret = getSecret();
  const fullPayload = nonce > 0 ? `${payload}|nonce=${nonce}` : payload;
  const hmac = createHmac("sha256", secret).update(fullPayload).digest("hex");
  return hmac.slice(0, HASH_LENGTH).toUpperCase();
}

function validateBranch(branch: string): void {
  if (!/^[A-Z]{3}$/.test(branch)) {
    throw new Error(`Kode branch harus 3 huruf kapital, diterima: "${branch}"`);
  }
}

function buildPrefix(domain: Domain, subType?: SubType): string {
  if (domain === "TCK") return "TCK";
  if (!subType) {
    throw new Error(`subType (REG/BLN) wajib diisi untuk domain ${domain}`);
  }
  return `${domain}-${subType}`;
}

/**
 * Generate ID hash tunggal (tanpa cek collision ke DB).
 * Gunakan `generateUniqueId` di bawah kalau ingin otomatis retry saat collision.
 */
export function generateHashId(input: GenerateHashIdInput, nonce = 0): string {
  const { domain, subType, branch, cid, date, refId } = input;
  validateBranch(branch);

  const prefix = buildPrefix(domain, subType);
  const branchUpper = branch.toUpperCase();
  const payload = `${domain}|${subType ?? ""}|${branchUpper}|${cid}|${date}|${refId}`;
  const hash = computeHash(payload, nonce);

  return `${prefix}-${branchUpper}-${hash}`;
}

/**
 * Fungsi cek ketersediaan ID ke database — diisi sesuai ORM/driver yang dipakai.
 * Return true kalau ID BELUM ada (aman dipakai), false kalau sudah ada (collision).
 */
export type ExistsChecker = (candidateId: string) => Promise<boolean>;

/**
 * Generate ID dengan otomatis retry kalau collision terdeteksi di database.
 * Collision secara teori sangat jarang (48-bit space), tapi tetap wajib di-handle
 * di sistem finansial — jangan asumsikan "hampir mustahil" = "tidak akan terjadi".
 */
export async function generateUniqueId(
  input: GenerateHashIdInput,
  checkNotExists: ExistsChecker,
  maxRetries = 5
): Promise<string> {
  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    const candidate = generateHashId(input, attempt);
    const isAvailable = await checkNotExists(candidate);
    if (isAvailable) return candidate;
  }
  throw new Error(
    `Gagal generate ID unik setelah ${maxRetries + 1} percobaan. Cek volume traffic atau pertimbangkan menambah HASH_LENGTH.`
  );
}

/**
 * Validasi apakah sebuah ID punya format yang sesuai skema (bentuk saja,
 * BUKAN validasi apakah datanya benar-benar ada — itu tetap wajib query DB).
 */
export function isValidIdFormat(id: string): boolean {
  const tckPattern = /^TCK-[A-Z]{3}-[0-9A-F]{12}$/;
  const invPayPattern = /^(INV|PAY)-(REG|BLN)-[A-Z]{3}-[0-9A-F]{12}$/;
  return tckPattern.test(id) || invPayPattern.test(id);
}

// ==================================================================
// Contoh pemakaian
// ==================================================================
//
// process.env.ID_HMAC_SECRET = "ganti-dengan-secret-kuat-minimal-32-char";
//
// generateHashId({
//   domain: "TCK",
//   branch: "JTS",
//   cid: "C1X4ARQ000004",
//   date: "25-07-2026",
//   refId: "0001",
// });
// => "TCK-JTS-A51F686EC0A8"  (nilai aktual tergantung SECRET yang dipakai)
//
// generateHashId({
//   domain: "INV",
//   subType: "BLN",
//   branch: "JTS",
//   cid: "C1X4ARQ000004",
//   date: "2026-08",
//   refId: "0105",
// });
// => "INV-BLN-JTS-<hash12>"
//
// // Dengan cek collision ke DB:
// const id = await generateUniqueId(
//   { domain: "PAY", subType: "REG", branch: "JTS", cid: "C1X4ARQ000004", date: "2026-08-03", refId: "0012" },
//   async (candidate) => {
//     const existing = await db.query("SELECT 1 FROM payments WHERE public_id = ?", [candidate]);
//     return existing.length === 0; // true = belum dipakai, aman
//   }
// );
//
// isValidIdFormat("TCK-JTS-A51F686EC0A8"); // => true
