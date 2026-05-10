Angular build ab .NET folder mein automatically nahi aata.

Angular alag folder mein build hota hai:
  qrcode-app-ui\dist\qrcode-app-ui\browser\

Sirf .NET + ek hi server se site serve karni ho to pehle Angular build karo, phir copy karo:
  PowerShell (repo root se):
    .\scripts\copy-angular-to-dotnet-wwwroot.ps1

Ya manually browser\ ke andar ki saari files yahan copy karo:
  wwwroot\browser\

PHP + Angular Hostinger par alag deploy karo — is wwwroot ki zaroorat nahi.
