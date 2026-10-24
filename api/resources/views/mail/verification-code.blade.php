<x-mail::message>
# Հաստատման կոդ

Ձեր էլ. փոստը հաստատելու կոդը՝

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

Կոդը գործում է {{ config('security.codes.ttl_minutes') }} րոպե։ Մի փոխանցեք այն ոչ ոքի, նույնիսկ Servio-ի աշխատակցին։

---

Код подтверждения email: **{{ $code }}**. Никому его не сообщайте.
</x-mail::message>
