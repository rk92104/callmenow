export interface QrScanResponse {
  publicId: string;
  status: string;
  productType: string;
  productLabel: string;
  scanCount: number;
  uniqueScannerCount: number;
  activatePageUrl: string;
  scanPageUrl: string;
  headline: string;
  subtitle: string;
  dialUri: string | null;
  /** Two-leg Exotel: collect scanner phone, POST /api/qr/.../exotel/connect-owner */
  ownerConnectViaExotel?: boolean;
  /** When true, API rings owner first, then scanner (see maskingNote). */
  exotelRingOwnerFirst?: boolean;
  /** When true, only owner is called via Exotel flow URL — scanner phone not used. */
  exotelOwnerOnlyAlert?: boolean;
  exotelAppId?: string;
  exotelCallerId?: string;
  /** tel: link to Exotel IVR DID when IVR is configured server-side */
  exotelIvrDialUri?: string | null;
  /** Six-digit code for Gather applet after dialing exotelIvrDialUri */
  ivrAccessCode?: string | null;
  ivrEmergencyAccessCode?: string | null;
  ownerNumberHiddenOnPage: boolean;
  maskingNote: string;
  trustedOwnersLine: string;
  regionTagline: string;
  referralCode: string;
  referralDiscountInr: number;
  stickerPriceInr: number;
  localizedCityLine: string;
  productVariants: ProductVariant[];
  sharePageUrl: string;
  /** Razorpay Checkout key (public). Present when server has keys configured. */
  razorpayEnabled?: boolean;
  razorpayKeyId?: string | null;
  vehicleRegistration?: string | null;
  emergencyDialUri?: string | null;
  emergencyContactMasked?: string | null;
  ownerPhoneMasked?: string | null;
  primaryPhoneType?: string | null;
  emergencyPhoneType?: string | null;
}

export interface ProductVariant {
  icon: string;
  name: string;
  skuHint: string;
}

export interface ActivateQrPayload {
  name: string;
  phoneNumber: string;
  phoneNumberType: 'Mobile' | 'Landline';
  email: string;
  address: string;
  fatherName: string;
  vehicleRegistration: string;
  emergencyContactPhone: string;
  emergencyContactPhoneType: 'Mobile' | 'Landline';
  paymentReference?: string;
  paymentCompleted: boolean;
  razorpayOrderId?: string;
  razorpayPaymentId?: string;
  razorpaySignature?: string;
}

export interface RazorpayOrderResponse {
  keyId: string;
  orderId: string;
  amount: number;
  currency: string;
  amountInr: number;
  referralApplied: boolean;
}

export interface QrInventoryItem {
  id: number;
  publicId: string;
  productType: string;
  status: string;
  scanCount: number;
  ownerPersonId: number | null;
  ownerName: string | null;
  /** Razorpay pay_… id or offline payment ref; null if unused / no ref. */
  paymentTransactionId: string | null;
  createdAt: string;
  activatedAt: string | null;
  activateUrl: string;
  scanUrl: string;
  packagingQrImageApi: string;
  stickerQrImageApi: string;
}

export interface QrInventoryListResponse {
  items: QrInventoryItem[];
  total: number;
  page: number;
  pageSize: number;
}
