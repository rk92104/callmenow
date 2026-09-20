import { Component, NgZone, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { QrService } from '../../services/qr.service';
import { QrScanResponse } from '../../models/qr.model';
import {
  normalizeVehicleRegistration,
  validatePersonContactFieldErrors,
  validatePersonContactPayload,
  vehicleRegistrationFeedback,
} from '../../utils/validation';

type RazorpaySuccess = {
  razorpay_payment_id: string;
  razorpay_order_id: string;
  razorpay_signature: string;
};

type RazorpayCtor = new (opts: Record<string, unknown>) => {
  open: () => void;
  on: (event: string, fn: (payload: unknown) => void) => void;
};

@Component({
  selector: 'app-activate-page',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './activate-page.html',
  styleUrl: './activate-page.css',
})
export class ActivatePageComponent implements OnInit {
  private static razorpayScriptPromise: Promise<void> | null = null;

  publicId = signal('');
  loading = signal(true);
  notFound = signal(false);
  alreadyActive = signal(false);
  submitting = signal(false);
  done = signal(false);
  scanUrl = signal<string | null>(null);
  errorMessage = signal<string | null>(null);
  fieldErrors = signal<Record<string, string>>({});
  scanData = signal<QrScanResponse | null>(null);
  paymentBusy = signal(false);

  form = {
    name: '',
    phoneNumber: '',
    phoneNumberType: 'Mobile' as 'Mobile' | 'Landline',
    email: '',
    address: '',
    fatherName: '',
    vehicleRegistration: '',
    emergencyContactPhone: '',
    emergencyContactPhoneType: 'Mobile' as 'Mobile' | 'Landline',
    paymentReference: '',
    paymentCompleted: false,
    referralCode: '',
    razorpayOrderId: '',
    razorpayPaymentId: '',
    razorpaySignature: '',
  };

  constructor(
    private readonly route: ActivatedRoute,
    private readonly qrService: QrService,
    private readonly ngZone: NgZone
  ) {}

  ngOnInit(): void {
    const raw = this.route.snapshot.paramMap.get('publicId') ?? '';
    const id = decodeURIComponent(raw);
    if (!id) {
      this.notFound.set(true);
      this.loading.set(false);
      return;
    }
    this.publicId.set(id);
    const refQ = this.route.snapshot.queryParamMap.get('ref');
    if (refQ) {
      this.form.referralCode = refQ.trim();
    }
    this.qrService.scan(id).subscribe({
      next: (res) => {
        this.scanData.set(res);
        if (res.status === 'active') {
          this.alreadyActive.set(true);
        }
        this.loading.set(false);
      },
      error: () => {
        this.notFound.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Shown price before opening Razorpay (server recalculates on order create). */
  effectiveAmountInr(): number {
    const d = this.scanData();
    if (!d) return 0;
    const ref = this.form.referralCode.trim().toUpperCase();
    const code = (d.referralCode ?? '').trim().toUpperCase();
    const discount = ref !== '' && ref === code ? d.referralDiscountInr : 0;
    return Math.max(1, d.stickerPriceInr - discount);
  }

  clearFieldError(field: string): void {
    if (this.fieldErrors()[field]) {
      this.fieldErrors.update((prev) => {
        const next = { ...prev };
        delete next[field];
        return next;
      });
    }
  }

  submit(): void {
    this.errorMessage.set(null);
    this.fieldErrors.set({});

    /* Commented out for now: Razorpay payment gateway requirement
    if (!this.form.paymentCompleted) {
      this.errorMessage.set('Use the Pay button to open Razorpay and complete payment first.');
      return;
    }
    */

    const contact = this.contactPayload();
    const errors = validatePersonContactFieldErrors(contact);
    if (Object.keys(errors).length > 0) {
      this.fieldErrors.set(errors);
      return;
    }

    this.submitting.set(true);
    this.qrService
      .activate(this.publicId(), {
        ...contact,
        paymentCompleted: true,
        /* Commented out for now: Razorpay payment reference parameters
        paymentReference: this.form.paymentReference.trim() || undefined,
        razorpayOrderId: this.form.razorpayOrderId.trim() || undefined,
        razorpayPaymentId: this.form.razorpayPaymentId.trim() || undefined,
        razorpaySignature: this.form.razorpaySignature.trim() || undefined,
        */
      })
      .subscribe({
        next: (res) => {
          this.done.set(true);
          this.scanUrl.set(res.scanUrl ?? null);
          this.submitting.set(false);
        },
        error: (err) => {
          if (err?.error?.errors && typeof err.error.errors === 'object') {
            const serverErrors: Record<string, string> = {};
            for (const [k, v] of Object.entries(err.error.errors)) {
              if (Array.isArray(v) && v.length > 0) {
                serverErrors[k] = String(v[0]);
              } else if (typeof v === 'string') {
                serverErrors[k] = v;
              }
            }
            this.fieldErrors.set(serverErrors);
          }
          const msg = err?.error?.message ?? 'Activation failed. Try again.';
          this.errorMessage.set(msg);
          this.submitting.set(false);
        },
      });
  }

  payWithRazorpay(): void {
    this.errorMessage.set(null);
    const contact = this.contactPayload();
    const fieldError = validatePersonContactPayload(contact);
    if (fieldError) {
      this.errorMessage.set('Please complete all details above before paying.');
      return;
    }
    this.paymentBusy.set(true);
    this.qrService.createRazorpayOrder(this.publicId(), this.form.referralCode || undefined).subscribe({
      next: (order) => {
        void this.openRazorpayCheckout(order);
      },
      error: (err) => {
        this.errorMessage.set(err?.error?.message ?? 'Could not start payment.');
        this.paymentBusy.set(false);
      },
    });
  }

  /**
   * No custom `config.display`: Razorpay’s default checkout shows UPI app intents (e.g. Google Pay on
   * Android Chrome) and QR; a custom block with show_default_blocks:false hid those for many users.
   */
  private async openRazorpayCheckout(order: {
    keyId: string;
    orderId: string;
    amount: number;
    currency: string;
  }): Promise<void> {
    try {
      await this.loadRazorpayScript();
    } catch {
      this.errorMessage.set('Could not load Razorpay checkout. Check internet or ad-blocker.');
      this.paymentBusy.set(false);
      return;
    }
    const w = window as unknown as { Razorpay?: RazorpayCtor };
    if (!w.Razorpay) {
      this.errorMessage.set('Razorpay checkout unavailable after loading script.');
      this.paymentBusy.set(false);
      return;
    }
    const contactDigits = this.form.phoneNumber.replace(/\D/g, '').slice(-15);
    // Amount + currency must match the order (Razorpay expects string amount in sub-units, e.g. paise for INR).
    const opts: Record<string, unknown> = {
      key: order.keyId,
      amount: String(order.amount),
      currency: order.currency || 'INR',
      name: 'CallMeNow',
      description: 'Sticker activation',
      order_id: order.orderId,
      prefill: {
        name: this.form.name.trim(),
        email: this.form.email.trim(),
        contact: contactDigits || undefined,
      },
      theme: { color: '#1c3d78' },
      modal: {
        ondismiss: () => {
          this.ngZone.run(() => this.paymentBusy.set(false));
        },
      },
      handler: (res: RazorpaySuccess) => {
        this.ngZone.run(() => {
          this.form.paymentCompleted = true;
          this.form.paymentReference = res.razorpay_payment_id;
          this.form.razorpayOrderId = res.razorpay_order_id;
          this.form.razorpayPaymentId = res.razorpay_payment_id;
          this.form.razorpaySignature = res.razorpay_signature;
          this.paymentBusy.set(false);
          this.submit();
        });
      },
    };
    try {
      const inst = new w.Razorpay(opts);
      inst.on('payment.failed', (fail: unknown) => {
        this.ngZone.run(() => {
          let msg = 'Payment failed.';
          if (fail && typeof fail === 'object' && 'error' in fail) {
            const e = (fail as { error?: { description?: string; reason?: string } }).error;
            msg = e?.description ?? e?.reason ?? msg;
          }
          this.errorMessage.set(msg);
          this.paymentBusy.set(false);
        });
      });
      await new Promise<void>((r) => setTimeout(r, 0));
      inst.open();
    } catch {
      this.errorMessage.set('Could not open Razorpay. Try again or refresh the page.');
      this.paymentBusy.set(false);
    }
  }

  private loadRazorpayScript(): Promise<void> {
    const g = window as unknown as { Razorpay?: RazorpayCtor };
    if (g.Razorpay) {
      return Promise.resolve();
    }
    if (!ActivatePageComponent.razorpayScriptPromise) {
      ActivatePageComponent.razorpayScriptPromise = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://checkout.razorpay.com/v1/checkout.js';
        s.async = true;
        s.onload = () => resolve();
        s.onerror = () => {
          ActivatePageComponent.razorpayScriptPromise = null;
          reject(new Error('Razorpay script failed'));
        };
        document.body.appendChild(s);
      });
    }
    return ActivatePageComponent.razorpayScriptPromise;
  }

  /** Trimmed contact fields for API + shared validation (registration normalized like server). */
  private contactPayload() {
    return {
      name: this.form.name.trim(),
      phoneNumber: this.form.phoneNumber.trim(),
      phoneNumberType: this.form.phoneNumberType,
      email: this.form.email.trim(),
      address: this.form.address.trim(),
      fatherName: this.form.fatherName.trim(),
      vehicleRegistration: normalizeVehicleRegistration(this.form.vehicleRegistration),
      emergencyContactPhone: this.form.emergencyContactPhone.trim(),
      emergencyContactPhoneType: this.form.emergencyContactPhoneType,
    };
  }

  printStickerLabelHref(): string {
    return this.qrService.printLabelHref(this.publicId(), 'scan');
  }

  /** Forces A–Z / 0–9 only and uppercase while typing. */
  onVehicleRegistrationInput(value: string): void {
    const n = normalizeVehicleRegistration(value);
    if (n !== value) {
      this.form.vehicleRegistration = n;
    }
  }

  vehicleRegistrationFieldMessage() {
    return vehicleRegistrationFeedback(this.form.vehicleRegistration);
  }
}
