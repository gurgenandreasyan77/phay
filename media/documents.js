// Documents for the AI agents: old office files (Word .doc, PowerPoint .ppt,
// Excel .xls, OpenDocument, Pages) become their modern twins with
// LibreOffice (POST /convert), so the API reads and fills them like any
// DOCX, PPTX or XLSX; a PDF gives its page count, its text and pictures of
// its first pages (POST /pdf) with poppler, for long PDFs and scans the
// model can't take whole. The file comes first through PUT /files.
//
// LibreOffice runs one conversion at a time, each with its own profile in a
// fresh folder that is removed after, and never touches the network.

import fsp from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { HttpError, filePath, run } from "./video.js";

const SOFFICE = process.env.SOFFICE_PATH || "soffice";
/** What each old format becomes. */
const TARGETS = {
  doc: "docx",
  dot: "docx",
  wps: "docx",
  wpd: "docx",
  pages: "docx",
  odt: "docx",
  rtf: "docx",
  ppt: "pptx",
  pps: "pptx",
  odp: "pptx",
  key: "pptx",
  xls: "xlsx",
  ods: "xlsx",
  numbers: "xlsx",
};
/** Pictures of a PDF's pages at most, and their sharpness. */
const MAX_IMAGES = 20;
const DPI = 80;

let converting = Promise.resolve();

/** One conversion after another (LibreOffice is heavy). */
function inLine(work) {
  const turn = converting.then(work, work);
  converting = turn.catch(() => null);
  return turn;
}

/** A document is read once: the uploaded copy goes right after (contracts don't wait for the sweep). */
function forget(file) {
  return fsp.rm(file, { force: true }).catch(() => null);
}

async function scratch() {
  return fsp.mkdtemp(path.join(os.tmpdir(), "phay-doc-"));
}

/** The file converted: {format, content (Buffer)}. */
export async function convert(id, from) {
  const source = filePath(id);
  const format = TARGETS[String(from).toLowerCase()];
  if (!format) {
    await forget(source);
    throw new HttpError(422, "This format can't be converted.");
  }

  return inLine(async () => {
    const dir = await scratch();
    try {
      const input = path.join(dir, `in.${String(from).toLowerCase()}`);
      await fsp.copyFile(source, input);
      try {
        await run(
          SOFFICE,
          [
            `-env:UserInstallation=file://${dir}/profile`,
            "--headless",
            "--norestore",
            "--nolockcheck",
            "--nodefault",
            "--convert-to",
            format,
            "--outdir",
            dir,
            input,
          ],
          { timeout: 90_000 },
        );
      } catch (error) {
        console.error("convert failed:", error.message);
        throw new HttpError(422, "The file could not be opened.");
      }
      const content = await fsp.readFile(path.join(dir, `in.${format}`)).catch(() => null);
      if (!content || content.length === 0) throw new HttpError(422, "The file could not be opened.");
      return { format, content };
    } finally {
      await fsp.rm(dir, { recursive: true, force: true });
      await forget(source);
    }
  });
}

/** A PDF's pages, text, and pictures of its first `images` pages (JPEG, base64). */
export async function pdf(id, images = 0) {
  const file = filePath(id);
  try {
    return await readPdf(file, images);
  } finally {
    await forget(file);
  }
}

async function readPdf(file, images) {
  let pages;
  try {
    const info = await run("pdfinfo", [file], { timeout: 30_000 });
    pages = Number(/^Pages:\s+(\d+)/m.exec(info)?.[1] || 0);
  } catch {
    throw new HttpError(422, "This is not a PDF that can be read.");
  }
  if (!(pages > 0)) throw new HttpError(422, "This is not a PDF that can be read.");

  const text = await run("pdftotext", ["-layout", "-enc", "UTF-8", file, "-"], { timeout: 60_000 }).catch(() => "");

  const shots = [];
  const count = Math.min(MAX_IMAGES, pages, Math.max(0, Math.floor(Number(images) || 0)));
  if (count > 0) {
    const dir = await scratch();
    try {
      await run("pdftoppm", ["-jpeg", "-jpegopt", "quality=70", "-r", String(DPI), "-f", "1", "-l", String(count), file, path.join(dir, "p")], {
        timeout: 120_000,
      });
      const names = (await fsp.readdir(dir)).filter((n) => n.endsWith(".jpg")).sort();
      for (const name of names) {
        shots.push({ page: Number(/(\d+)\.jpg$/.exec(name)?.[1] || shots.length + 1), jpg: (await fsp.readFile(path.join(dir, name))).toString("base64") });
      }
    } finally {
      await fsp.rm(dir, { recursive: true, force: true });
    }
  }

  return { pages, text, images: shots };
}

export const CONVERTIBLE = Object.keys(TARGETS);
