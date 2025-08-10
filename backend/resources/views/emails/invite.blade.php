@component('mail::message')
# 🎯 ¡Te han invitado a unirte a GolfApp!

Hola,

Has sido invitado a formar parte de **GolfApp** como **{{ ucfirst(str_replace('_', ' ', $invitation->role)) }}**.

Este rol te dará acceso a funciones específicas dentro de la plataforma para ayudarte a cumplir tus objetivos y colaborar con tu equipo.

---

@component('mail::panel')
🎟️ **Correo invitado:** {{ $invitation->email }}  
⏳ **Este enlace expira en:** {{ \Carbon\Carbon::parse($invitation->expires_at)->diffForHumans() }}
@endcomponent

---

@component('mail::button', ['url' => url("/finish-registration/{$invitation->token}")])
📥 Completar Registro
@endcomponent

Si no esperabas esta invitación, podés ignorar este correo.

Gracias,<br>
**El equipo de GolfApp 🏌️‍♂️**

@endcomponent
