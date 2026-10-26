import { notFound } from "next/navigation";

/**
 * Any /admin address no screen answers: the admin's own 404 (not-found.tsx,
 * inside the admin's frame) instead of Next's default page.
 */
export default function UnknownAdminPage() {
  notFound();
}
