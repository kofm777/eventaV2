import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../services/api.service';
import { Participant } from '../../models/participant.model';

@Component({
  selector: 'app-admin-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './admin-list.component.html',
  styleUrls: ['./admin-list.component.css']
})
export class AdminListComponent implements OnInit {
  participants: Participant[] = [];
  loading = false;
  error: string | null = null;
  success: string | null = null;
  showModal = false;
  modalMessage = '';
  private pendingAction: (() => void) | null = null;
  private currentParticipant: Participant | null = null;
  // Filters
  statusFilter = '';
  accessTypeFilter = '';
  searchQuery = '';

  // Pagination
  currentPage = 1;
  lastPage = 1;
  total = 0;

  constructor(private apiService: ApiService) {}

  ngOnInit(): void {
    this.loadParticipants();
  }

  loadParticipants(): void {
    this.loading = true;
    this.error = null;

    const filters = {
      status: this.statusFilter,
      access_type: this.accessTypeFilter,
      search: this.searchQuery,
      page: this.currentPage
    };

    this.apiService.getParticipants(filters).subscribe({
      next: (response) => {
        this.loading = false;
        if (response.ok) {
          this.participants = response.participants.data;
          this.currentPage = response.participants.current_page;
          this.lastPage = response.participants.last_page;
          this.total = response.participants.total;
        }
      },
      error: (err) => {
        this.loading = false;
        this.error = 'Error loading participants.';
        console.error(err);
      }
    });
  }

  applyFilters(): void {
    this.currentPage = 1;
    this.loadParticipants();
  }

  clearFilters(): void {
    this.statusFilter = '';
    this.accessTypeFilter = '';
    this.searchQuery = '';
    this.currentPage = 1;
    this.loadParticipants();
  }

  acceptParticipant(participant: Participant): void {
    const accessLabel = this.getAccessTypeLabel(participant.access_type);
    this.modalMessage = `Are you sure you want to <strong>accept</strong> participant <strong>${participant.first_name} ${participant.last_name}</strong> with <strong>${accessLabel}</strong> access?`;
    this.currentParticipant = participant;
    this.pendingAction = () => this.performAccept(participant);
    this.showModal = true;
  }

  private performAccept(participant: Participant): void {
    this.apiService.acceptParticipant(participant.id).subscribe({
      next: (response) => {
        if (response.ok) {
          this.success = `${participant.first_name} ${participant.last_name} has been accepted.`;
          this.loadParticipants();
          setTimeout(() => this.success = null, 3000);
        }
      },
      error: (err) => {
        this.error = err.error?.message || 'Error during acceptance.';
        setTimeout(() => this.error = null, 3000);
      }
    });
  }

  rejectParticipant(participant: Participant): void {
    const accessLabel = this.getAccessTypeLabel(participant.access_type);
    this.modalMessage = `Are you sure you want to <strong>reject</strong> participant <strong>${participant.first_name} ${participant.last_name}</strong> with <strong>${accessLabel}</strong> access?`;
    this.currentParticipant = participant;
    this.pendingAction = () => this.performReject(participant);
    this.showModal = true;
  }

  private performReject(participant: Participant): void {
    this.apiService.rejectParticipant(participant.id).subscribe({
      next: (response) => {
        if (response.ok) {
          this.success = `${participant.first_name} ${participant.last_name} has been rejected.`;
          this.loadParticipants();
          setTimeout(() => this.success = null, 3000);
        }
      },
      error: (err) => {
        this.error = err.error?.message || 'Error during rejection.';
        setTimeout(() => this.error = null, 3000);
      }
    });
  }

  deleteParticipant(participant: Participant): void {
    const accessLabel = this.getAccessTypeLabel(participant.access_type);
    this.modalMessage = `Are you sure you want to <strong>permanently delete</strong> participant <strong>${participant.first_name} ${participant.last_name}</strong> with <strong>${accessLabel}</strong> access?`;
    this.currentParticipant = participant;
    this.pendingAction = () => this.performDelete(participant);
    this.showModal = true;
  }

  private performDelete(participant: Participant): void {
    this.apiService.deleteParticipant(participant.id).subscribe({
      next: (response) => {
        if (response.ok) {
          this.success = `${participant.first_name} ${participant.last_name} has been deleted.`;
          this.loadParticipants();
          setTimeout(() => this.success = null, 3000);
        }
      },
      error: (err) => {
        this.error = err.error?.message || 'Error during deletion.';
        setTimeout(() => this.error = null, 3000);
      }
    });
  }
  confirmAction(): void {
    if (this.pendingAction) {
      this.pendingAction();
    }
    this.closeModal();
  }

  closeModal(): void {
    this.showModal = false;
    this.modalMessage = '';
    this.pendingAction = null;
    this.currentParticipant = null;
  }
  downloadBadge(participant: Participant): void {
    this.apiService.downloadBadge(participant.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `badge-${participant.first_name}-${participant.last_name}.pdf`;
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => {
        this.error = 'Error downloading badge.';
        setTimeout(() => this.error = null, 3000);
      }
    });
  }

  getStatusLabel(status: string): string {
    switch (status) {
      case 'pending': return 'Pending';
      case 'accepted': return 'Accepted';
      case 'rejected': return 'Rejected';
      case 'fair_scanned': return 'Fair Scanned';
      case 'conference_scanned': return 'Conference Scanned';
      default: return status;
    }
  }

  getStatusBadgeClass(status: string): string {
    switch (status) {
      case 'pending': return 'badge-pending';
      case 'accepted': return 'badge-accepted';
      case 'rejected': return 'badge-rejected';
      case 'fair_scanned': return 'badge-fair_scanned';
      case 'conference_scanned': return 'badge-conference_scanned';
      default: return '';
    }
  }

  getAccessTypeLabel(accessType: string): string {
    switch (accessType) {
      case 'fair':
        return 'Fair';
      case 'conference':
        return 'Conference';
      case 'fair + conference':
        return 'Fair + Conference';
      default:
        return accessType;
    }
  }

  previousPage(): void {
    if (this.currentPage > 1) {
      this.currentPage--;
      this.loadParticipants();
    }
  }

  nextPage(): void {
    if (this.currentPage < this.lastPage) {
      this.currentPage++;
      this.loadParticipants();
    }
  }

  copyToClipboard(text: string): void {
    navigator.clipboard.writeText(text).then(() => {
      alert('QR Token copied to clipboard!');
    }).catch(err => {
      console.error('Error copying:', err);
      // Fallback for older browsers
      const textArea = document.createElement('textarea');
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      alert('QR Token copied to clipboard!');
    });
  }

  downloadQrImage(participant: any): void {
    if (!participant.qr_image) return;

    const link = document.createElement('a');
    link.href = `data:image/png;base64,${participant.qr_image}`;
    link.download = `qr-code-${participant.first_name}-${participant.last_name}.png`;
    link.click();
  }
}
