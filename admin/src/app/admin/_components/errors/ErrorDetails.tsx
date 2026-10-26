/**
 * For the developer and for support: the error's digest (the same id is in
 * the admin's server log) and, in development only, its message.
 */
export function ErrorDetails({ error }: { error: Error & { digest?: string } }) {
  const dev = process.env.NODE_ENV !== "production";
  if (!error.digest && !dev) return null;

  return (
    <dl className="space-y-1.5">
      {error.digest ? (
        <div className="flex gap-2">
          <dt className="shrink-0 font-medium">Սխալի կոդ՝</dt>
          <dd className="break-all font-mono">{error.digest}</dd>
        </div>
      ) : null}
      {dev && error.message ? (
        <div>
          <dt className="font-medium">Մանրամասներ (միայն մշակման ռեժիմում)՝</dt>
          <dd className="mt-1 whitespace-pre-wrap break-words font-mono text-danger">{error.message}</dd>
        </div>
      ) : null}
    </dl>
  );
}
