"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useRouter } from "next/navigation";
import type { ServiceDto } from "@/lib/api";
import { Button, Input, Select, Textarea } from "../../_components/ui";

const PHONE_PATTERN = /^(\+374\d{8}|0\d{8}|\+[1-9]\d{7,14})$/;
const AREA_PATTERN = /^\d+$/;

function isValidPhone(value: string): boolean {
  return PHONE_PATTERN.test(value.replace(/[\s\-()]/g, ""));
}

function isValidArea(value: string | undefined): boolean {
  if (!value) return true;
  return AREA_PATTERN.test(value) && Number(value) >= 1 && Number(value) <= 100000;
}

const schema = z.object({
  name: z.string().trim().min(2, "Չափազանց կարճ է։").max(100, "Չափազանց երկար է։"),
  phone: z
    .string()
    .min(1, "Այս դաշտը պարտադիր է։")
    .max(30, "Չափազանց երկար է։")
    .refine(isValidPhone, "Խնդրում ենք նշել վավեր հեռախոսահամար։"),
  email: z.string().max(255).optional(),
  service_slug: z.string().optional(),
  property_type: z.string().optional(),
  area_sqm: z.string().optional().refine(isValidArea, "Խնդրում ենք նշել 1-ից 100000 միջակայքում արժեք։"),
  address: z.string().max(255).optional(),
  preferred_date: z.string().optional(),
  comment: z.string().max(1000).optional(),
});

type FormValues = z.infer<typeof schema>;

const labelClasses = "text-sm font-medium text-ink";
const errorClasses = "text-sm text-danger";

export function NewOrderForm({ services }: { services: ServiceDto[] }) {
  const router = useRouter();
  const [status, setStatus] = useState<"idle" | "submitting" | "error">("idle");

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "",
      phone: "",
      email: "",
      service_slug: "",
      property_type: "",
      area_sqm: "",
      address: "",
      preferred_date: "",
      comment: "",
    },
  });

  const areaField = register("area_sqm");

  const onSubmit = async (values: FormValues) => {
    setStatus("submitting");

    try {
      const response = await fetch("/api/admin/leads", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: values.name,
          phone: values.phone,
          email: values.email || undefined,
          service_slug: values.service_slug || undefined,
          property_type: values.property_type || undefined,
          area_sqm: values.area_sqm ? Number(values.area_sqm) : undefined,
          address: values.address || undefined,
          preferred_date: values.preferred_date || undefined,
          comment: values.comment || undefined,
        }),
      });

      if (!response.ok) {
        setStatus("error");
        return;
      }

      router.push("/admin/orders");
      router.refresh();
    } catch {
      setStatus("error");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-5">
      <div className="grid gap-5 sm:grid-cols-2">
        <div className="space-y-1.5">
          <label htmlFor="name" className={labelClasses}>
            Անուն
          </label>
          <Input id="name" type="text" {...register("name")} />
          {errors.name ? <p className={errorClasses}>{errors.name.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="phone" className={labelClasses}>
            Հեռախոսահամար
          </label>
          <Input id="phone" type="tel" placeholder="+374 XX XXXXXX" {...register("phone")} />
          {errors.phone ? <p className={errorClasses}>{errors.phone.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="email" className={labelClasses}>
            Էլ. փոստ (ոչ պարտադիր)
          </label>
          <Input id="email" type="email" {...register("email")} />
        </div>

        <div className="space-y-1.5">
          <label htmlFor="service_slug" className={labelClasses}>
            Ծառայություն
          </label>
          <Select id="service_slug" {...register("service_slug")}>
            <option value="">Ընտրեք ծառայությունը</option>
            {services.map((service) => (
              <option key={service.slug} value={service.slug}>
                {service.name}
              </option>
            ))}
          </Select>
        </div>

        <div className="space-y-1.5">
          <label htmlFor="property_type" className={labelClasses}>
            Օբյեկտի տեսակ
          </label>
          <Select id="property_type" {...register("property_type")}>
            <option value=""></option>
            <option value="apartment">Բնակարան</option>
            <option value="house">Առանձնատուն</option>
            <option value="office">Գրասենյակ</option>
            <option value="other">Այլ</option>
          </Select>
        </div>

        <div className="space-y-1.5">
          <label htmlFor="area_sqm" className={labelClasses}>
            Մակերես, մ² (մոտավոր)
          </label>
          <Input
            id="area_sqm"
            type="text"
            inputMode="numeric"
            {...areaField}
            onChange={(e) => {
              e.target.value = e.target.value.replace(/\D/g, "");
              areaField.onChange(e);
            }}
          />
          {errors.area_sqm ? <p className={errorClasses}>{errors.area_sqm.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="address" className={labelClasses}>
            Հասցե կամ թաղամաս
          </label>
          <Input id="address" type="text" {...register("address")} />
        </div>

        <div className="space-y-1.5">
          <label htmlFor="preferred_date" className={labelClasses}>
            Ցանկալի ամսաթիվ
          </label>
          <Input id="preferred_date" type="date" {...register("preferred_date")} />
        </div>
      </div>

      <div className="space-y-1.5">
        <label htmlFor="comment" className={labelClasses}>
          Մեկնաբանություն
        </label>
        <Textarea id="comment" rows={4} {...register("comment")} />
      </div>

      {status === "error" ? (
        <div className="rounded-md border border-danger bg-danger-light p-4">
          <p className="text-sm font-medium text-danger">Չհաջողվեց ավելացնել պատվերը։ Փորձեք կրկին։</p>
        </div>
      ) : null}

      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting || status === "submitting" ? "Ավելացվում է…" : "Ավելացնել պատվեր"}
      </Button>
    </form>
  );
}
