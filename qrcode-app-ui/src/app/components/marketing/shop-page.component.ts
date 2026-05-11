import { Component, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { EcommService } from '../../services/ecomm.service';
import { HttpClient } from '@angular/common/http';
import { QrService } from '../../services/qr.service';
import { NgZone } from '@angular/core';

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
  selector: 'app-shop-page',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './shop-page.component.html',
  styleUrl: './shop-page.component.css'
})
export class ShopPageComponent {
  constructor(
    private readonly ecomm: EcommService,
    private readonly qrService: QrService,
    private readonly ngZone: NgZone
  ) {}

  private static razorpayScriptPromise: Promise<void> | null = null;

  products = [
    {
      id: 'single',
      name: 'Solo Pack',
      description: '1 Premium QR Sticker for your vehicle.',
      price: 299,
      image: '/marketing/hero-vehicle-qr.png',
      features: ['Weather-resistant', 'UV Protected', 'Scratch proof']
    },
    {
      id: 'family',
      name: 'Family Pack',
      description: '3 QR Stickers for all your home vehicles.',
      price: 799,
      image: '/marketing/hero-slide-03.png',
      features: ['Saves ₹100', 'Weather-resistant', 'UV Protected']
    },
    {
      id: 'bulk',
      name: 'Fleet / Bulk',
      description: '10+ stickers for transport or corporate fleets.',
      price: 1999,
      image: '/marketing/hero-slide-04.png',
      features: ['Wholesale pricing', 'Dedicated Support', 'Express Shipping']
    }
  ];

  cart = signal<{productId: string, name: string, price: number, quantity: number}[]>([]);
  orderStep = signal<number>(1); // 1: Selection, 2: Address, 3: Success
  showCart = signal<boolean>(false);

  states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 
    'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 
    'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 
    'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh', 
    'Dadra and Nagar Haveli', 'Daman and Diu', 'Delhi', 'Lakshadweep', 'Puducherry'
  ];

  citiesByState: { [key: string]: string[] } = {
    'Maharashtra': ['Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Aurangabad', 'Thane'],
    'Delhi': ['New Delhi', 'North Delhi', 'South Delhi', 'East Delhi', 'West Delhi'],
    'Karnataka': ['Bengaluru', 'Mysuru', 'Hubballi', 'Mangaluru', 'Belagavi'],
    'Gujarat': ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Bhavnagar'],
    'Uttar Pradesh': ['Lucknow', 'Kanpur', 'Varanasi', 'Agra', 'Meerut', 'Noida'],
    'Tamil Nadu': ['Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem'],
    'West Bengal': ['Kolkata', 'Howrah', 'Durgapur', 'Asansol', 'Siliguri'],
    'Rajasthan': ['Jaipur', 'Jodhpur', 'Udaipur', 'Kota', 'Ajmer'],
    'Punjab': ['Ludhiana', 'Amritsar', 'Jalandhar', 'Patiala', 'Bathinda', 'Mohali', 'Pathankot', 'Hoshiarpur', 'Moga'],
    'Bihar': ['Patna', 'Gaya', 'Bhagalpur', 'Muzaffarpur'],
    'Telangana': ['Hyderabad', 'Warangal', 'Nizamabad', 'Khammam'],
    'Kerala': ['Thiruvananthapuram', 'Kochi', 'Kozhikode', 'Thrissur'],
    'Haryana': ['Gurugram', 'Faridabad', 'Panipat', 'Ambala', 'Hisar']
  };

  private http = inject(HttpClient);
  filteredCities = signal<string[]>([]);

  shippingDetails = {
    name: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    pincode: ''
  };

  onStateChange() {
    const cities = this.citiesByState[this.shippingDetails.state] || [];
    this.filteredCities.set(cities);
    this.shippingDetails.city = '';
  }

  // Auto-fetch State/City from Pincode
  onPincodeChange() {
    const pin = this.shippingDetails.pincode;
    if (pin.length === 6) {
      this.http.get<any>(`https://api.postalpincode.in/pincode/${pin}`).subscribe({
        next: (data) => {
          if (data?.[0]?.Status === 'Success') {
            const details = data[0].PostOffice[0];
            const apiState = details.State;
            const apiDistrict = details.District;

            // Case-insensitive state matching
            const matchedState = this.states.find(s => s.toLowerCase() === apiState.toLowerCase());
            
            if (matchedState) {
              this.shippingDetails.state = matchedState;
              this.onStateChange();
            }
            
            // If the city from the API is not in the dropdown list, add it dynamically
            if (!this.filteredCities().includes(apiDistrict)) {
              this.filteredCities.update(cities => [...cities, apiDistrict].sort());
            }
            
            this.shippingDetails.city = apiDistrict;
          }
        }
      });
    }
  }

  get cartTotal() {
    return this.cart().reduce((sum, item) => sum + (item.price * item.quantity), 0);
  }

  get cartCount() {
    return this.cart().reduce((sum, item) => sum + item.quantity, 0);
  }

  addToCart(productId: string) {
    const product = this.products.find(p => p.id === productId);
    if (!product) return;

    this.cart.update(items => {
      const existing = items.find(i => i.productId === productId);
      if (existing) {
        return items.map(i => i.productId === productId ? {...i, quantity: i.quantity + 1} : i);
      }
      return [...items, { productId: product.id, name: product.name, price: product.price, quantity: 1 }];
    });
    this.showCart.set(true);
  }

  removeFromCart(productId: string) {
    this.cart.update(items => items.filter(i => i.productId !== productId));
  }

  updateQuantity(productId: string, delta: number) {
    this.cart.update(items => items.map(i => {
      if (i.productId === productId) {
        const newQty = Math.max(1, i.quantity + delta);
        return { ...i, quantity: newQty };
      }
      return i;
    }));
  }

  toggleCart() {
    this.showCart.update(v => !v);
  }

  nextStep() {
    if (this.cart().length === 0) {
      alert('Please add at least one item to your cart.');
      return;
    }
    if (this.orderStep() === 1) {
      this.orderStep.set(2);
      this.showCart.set(false);
    }
  }

  prevStep() {
    if (this.orderStep() > 1) {
      this.orderStep.update(s => s - 1);
    }
  }

  isProcessingPayment = signal<boolean>(false);

  onSubmitOrder(form: any) {
    if (this.cart().length === 0) return;

    if (form.invalid) {
      Object.keys(form.controls).forEach(key => {
        form.controls[key].markAsTouched();
      });
      return;
    }

    this.isProcessingPayment.set(true);

    // 1. Create Razorpay Order
    this.qrService.createRazorpayShopOrder(this.cartTotal).subscribe({
      next: (order) => {
        void this.openRazorpayCheckout(order);
      },
      error: (err) => {
        console.error('Razorpay order creation failed', err);
        alert(err?.error?.message ?? 'Could not start payment. Please try again.');
        this.isProcessingPayment.set(false);
      }
    });
  }

  private async openRazorpayCheckout(order: any): Promise<void> {
    try {
      await this.loadRazorpayScript();
    } catch {
      alert('Could not load Razorpay checkout. Check internet or ad-blocker.');
      this.isProcessingPayment.set(false);
      return;
    }

    const w = window as unknown as { Razorpay?: RazorpayCtor };
    if (!w.Razorpay) {
      alert('Razorpay checkout unavailable after loading script.');
      this.isProcessingPayment.set(false);
      return;
    }

    const contactDigits = this.shippingDetails.phone.replace(/\D/g, '').slice(-15);
    
    const opts: Record<string, unknown> = {
      key: order.keyId,
      amount: String(order.amount),
      currency: order.currency || 'INR',
      name: 'CallMeNow',
      description: 'Sticker Purchase',
      order_id: order.orderId,
      prefill: {
        name: this.shippingDetails.name.trim(),
        contact: contactDigits || undefined,
      },
      theme: { color: '#1c3d78' },
      modal: {
        ondismiss: () => {
          this.ngZone.run(() => this.isProcessingPayment.set(false));
        },
      },
      handler: (res: RazorpaySuccess) => {
        this.ngZone.run(() => {
          this.finalizeBooking(res);
        });
      },
    };

    try {
      const inst = new w.Razorpay(opts);
      inst.on('payment.failed', (fail: any) => {
        this.ngZone.run(() => {
          let msg = 'Payment failed.';
          if (fail && typeof fail === 'object' && 'error' in fail) {
            const e = fail.error;
            msg = e?.description ?? e?.reason ?? msg;
          }
          alert(msg);
          this.isProcessingPayment.set(false);
        });
      });
      await new Promise<void>((r) => setTimeout(r, 0));
      inst.open();
    } catch {
      alert('Could not open Razorpay. Try again or refresh the page.');
      this.isProcessingPayment.set(false);
    }
  }

  private finalizeBooking(paymentRes: RazorpaySuccess) {
    this.orderStep.set(3); // Show processing screen
    
    const orderData = {
      customerName: this.shippingDetails.name,
      customerPhone: this.shippingDetails.phone,
      shippingAddress: this.shippingDetails.address,
      city: this.shippingDetails.city,
      pincode: this.shippingDetails.pincode,
      productId: this.cart()[0].productId, 
      productName: this.cart().map(i => `${i.quantity}x ${i.name}`).join(', '),
      amount: this.cartTotal,
      razorpayOrderId: paymentRes.razorpay_order_id,
      razorpayPaymentId: paymentRes.razorpay_payment_id,
      razorpaySignature: paymentRes.razorpay_signature
    };

    this.ecomm.bookSticker(orderData).subscribe({
      next: () => {
        setTimeout(() => {
          this.isProcessingPayment.set(false);
          this.orderStep.set(4); // Move to final success
        }, 1500);
      },
      error: (err) => {
        this.isProcessingPayment.set(false);
        this.orderStep.set(2);
        console.error('Order failed', err);
        alert('Something went wrong recording your payment. Access was verified but order save failed. Contact support with reference: ' + paymentRes.razorpay_payment_id);
      }
    });
  }

  private loadRazorpayScript(): Promise<void> {
    const g = window as unknown as { Razorpay?: RazorpayCtor };
    if (g.Razorpay) {
      return Promise.resolve();
    }
    if (!ShopPageComponent.razorpayScriptPromise) {
      ShopPageComponent.razorpayScriptPromise = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://checkout.razorpay.com/v1/checkout.js';
        s.async = true;
        s.onload = () => resolve();
        s.onerror = () => {
          ShopPageComponent.razorpayScriptPromise = null;
          reject(new Error('Razorpay script failed'));
        };
        document.body.appendChild(s);
      });
    }
    return ShopPageComponent.razorpayScriptPromise;
  }
}

