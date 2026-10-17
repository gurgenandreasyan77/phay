"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useRouter } from "next/navigation";
import { Button, Card, Input } from "../_components/ui";

const schema = z.object({
  email: z.string().min(1, "Այս դաշտը պարտադիր է։").email("Խնդրում ենք նշել վավեր էլ. հասցե։"),
  password: z.string().min(1, "Այս դաշտը պարտադիր է։"),
});

type FormValues = z.infer<typeof schema>;

const labelClasses = "text-sm font-medium text-ink";
const errorClasses = "text-sm text-danger";

export default function AdminLoginPage() {
  const router = useRouter();
  const [status, setStatus] = useState<"idle" | "submitting" | "error">("idle");

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", password: "" },
  });

  const onSubmit = async (values: FormValues) => {
    setStatus("submitting");

    try {
      const response = await fetch("/api/admin/session", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(values),
      });

      if (!response.ok) {
        setStatus("error");
        return;
      }

      router.push("/admin");
      router.refresh();
    } catch {
      setStatus("error");
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center px-6 py-16">
      <div className="w-full max-w-sm space-y-6">
        <div className="flex flex-col items-center gap-3 text-center">
          <span className="flex h-11 w-11 items-center justify-center rounded-md bg-primary text-base font-semibold text-primary-foreground">
            S
          </span>
          <div>
            <h1 className="text-xl font-semibold text-ink">Մուտք</h1>
            <p className="mt-1 text-sm text-ink-muted">Մուտք գործեք ադմին վահանակ։</p>
          </div>
        </div>

        <Card>
          <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-5">
            <div className="space-y-1.5">
              <label htmlFor="email" className={labelClasses}>
                Էլ. փոստ
              </label>
              <Input id="email" type="email" {...register("email")} />
              {errors.email ? <p className={errorClasses}>{errors.email.message}</p> : null}
            </div>

            <div className="space-y-1.5">
              <label htmlFor="password" className={labelClasses}>
                Գաղտնաբառ
              </label>
              <Input id="password" type="password" {...register("password")} />
              {errors.password ? <p className={errorClasses}>{errors.password.message}</p> : null}
            </div>

            {status === "error" ? (
              <div className="rounded-md border border-danger bg-danger-light p-3.5">
                <p className="text-sm font-medium text-danger">Սխալ էլ. հասցե կամ գաղտնաբառ։</p>
              </div>
            ) : null}

            <Button type="submit" disabled={isSubmitting} className="w-full">
              {isSubmitting || status === "submitting" ? "Մուտք…" : "Մուտք գործել"}
            </Button>
          </form>
        </Card>
      </div>
    </div>
  );
}
