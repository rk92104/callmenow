import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { PersonService } from '../../services/person.service';
import { PersonDto } from '../../models/person.model';
import {
    normalizeVehicleRegistration,
    validatePersonContactPayload,
    vehicleRegistrationFeedback,
} from '../../utils/validation';

@Component({
    selector: 'app-person-form',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterLink],
    templateUrl: './person-form.html',
    styleUrl: './person-form.css'
})
export class PersonFormComponent implements OnInit {
    isEditMode = signal(false);
    personId = signal<number | null>(null);
    loading = signal(false);
    submitting = signal(false);
    notification = signal<{ message: string; type: 'success' | 'error' } | null>(null);

    formData: PersonDto = {
        name: '',
        phoneNumber: '',
        phoneNumberType: 'Mobile',
        email: '',
        address: '',
        fatherName: '',
        vehicleRegistration: '',
        emergencyContactPhone: '',
        emergencyContactPhoneType: 'Mobile'
    };

    constructor(
        private readonly personService: PersonService,
        private readonly route: ActivatedRoute,
        private readonly router: Router
    ) { }

    ngOnInit(): void {
        const id = this.route.snapshot.paramMap.get('id');
        if (id) {
            this.isEditMode.set(true);
            this.personId.set(+id);
            this.loadPerson(+id);
        }
    }

    loadPerson(id: number): void {
        this.loading.set(true);
        this.personService.getById(id).subscribe({
            next: (person) => {
                this.formData = {
                    name: person.name,
                    phoneNumber: person.phoneNumber,
                    phoneNumberType: person.phoneNumberType ?? 'Mobile',
                    email: person.email,
                    address: person.address,
                    fatherName: person.fatherName,
                    vehicleRegistration: person.vehicleRegistration ?? '',
                    emergencyContactPhone: person.emergencyContactPhone ?? '',
                    emergencyContactPhoneType: person.emergencyContactPhoneType ?? 'Mobile'
                };
                this.loading.set(false);
            },
            error: (err) => {
                console.error('Error loading person:', err);
                this.loading.set(false);
                this.showNotification('Failed to load person data.', 'error');
            }
        });
    }

    onSubmit(): void {
        const payload = this.buildPayload();
        const validationError = validatePersonContactPayload(payload);
        if (validationError) {
            this.showNotification(validationError, 'error');
            return;
        }

        this.submitting.set(true);

        if (this.isEditMode()) {
            this.personService.update(this.personId()!, payload).subscribe({
                next: () => {
                    this.submitting.set(false);
                    this.showNotification('Contact updated successfully!', 'success');
                    setTimeout(() => this.router.navigate(['/app/contacts']), 1200);
                },
                error: (err) => {
                    console.error('Error updating person:', err);
                    this.submitting.set(false);
                    this.showNotification(this.apiErr(err, 'Failed to update contact.'), 'error');
                }
            });
        } else {
            this.personService.create(payload).subscribe({
                next: () => {
                    this.submitting.set(false);
                    this.showNotification('Contact created successfully!', 'success');
                    setTimeout(() => this.router.navigate(['/app/contacts']), 1200);
                },
                error: (err) => {
                    console.error('Error creating person:', err);
                    this.submitting.set(false);
                    this.showNotification(this.apiErr(err, 'Failed to create contact.'), 'error');
                }
            });
        }
    }

    /** Trim + normalize so every Person column is persisted consistently. */
    private buildPayload(): PersonDto {
        const v = this.formData;
        return {
            name: v.name.trim(),
            phoneNumber: v.phoneNumber.trim(),
            phoneNumberType: v.phoneNumberType === 'Landline' ? 'Landline' : 'Mobile',
            email: v.email.trim(),
            address: v.address.trim(),
            fatherName: v.fatherName.trim(),
            vehicleRegistration: normalizeVehicleRegistration(v.vehicleRegistration),
            emergencyContactPhone: v.emergencyContactPhone.trim(),
            emergencyContactPhoneType: v.emergencyContactPhoneType === 'Landline' ? 'Landline' : 'Mobile',
        };
    }

    private apiErr(err: unknown, fallback: string): string {
        const e = err as { error?: { message?: string } };
        const m = e?.error?.message;
        return typeof m === 'string' && m.length > 0 ? m : fallback;
    }

    isFormValid(): boolean {
        return validatePersonContactPayload(this.buildPayload()) === null;
    }

    showNotification(message: string, type: 'success' | 'error'): void {
        this.notification.set({ message, type });
        setTimeout(() => this.notification.set(null), 4000);
    }

    onVehicleRegistrationInput(value: string): void {
        const n = normalizeVehicleRegistration(value);
        if (n !== value) {
            this.formData.vehicleRegistration = n;
        }
    }

    vehicleRegistrationFieldMessage() {
        return vehicleRegistrationFeedback(this.formData.vehicleRegistration);
    }
}
