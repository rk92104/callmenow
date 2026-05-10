export interface Person {
  id: number;
  name: string;
  phoneNumber: string;
  phoneNumberType: string;
  email: string;
  address: string;
  fatherName: string;
  vehicleRegistration: string;
  emergencyContactPhone: string;
  emergencyContactPhoneType: string;
  createdAt: string;
  updatedAt: string | null;
}

export interface PersonDto {
  name: string;
  phoneNumber: string;
  phoneNumberType: string;
  email: string;
  address: string;
  fatherName: string;
  vehicleRegistration: string;
  emergencyContactPhone: string;
  emergencyContactPhoneType: string;
}

export interface QRCodeResponse {
  qrCodeImage: string;
  qrText: string;
  publicId?: string;
}
