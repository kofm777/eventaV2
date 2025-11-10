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
        this.error = 'Erreur lors du chargement des participants.';
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
    if (!confirm(`Accepter l'inscription de ${participant.first_name} ${participant.last_name} ?`)) {
      return;
    }

    this.apiService.acceptParticipant(participant.id).subscribe({
      next: (response) => {
        if (response.ok) {
          this.success = `${participant.first_name} ${participant.last_name} a été accepté(e).`;
          this.loadParticipants();
          setTimeout(() => this.success = null, 3000);
        }
      },
      error: (err) => {
        this.error = err.error?.message || 'Erreur lors de l\'acceptation.';
        setTimeout(() => this.error = null, 3000);
      }
    });
  }

  rejectParticipant(participant: Participant): void {
    if (!confirm(`Rejeter l'inscription de ${participant.first_name} ${participant.last_name} ?`)) {
      return;
    }

    this.apiService.rejectParticipant(participant.id).subscribe({
      next: (response) => {
        if (response.ok) {
          this.success = `${participant.first_name} ${participant.last_name} a été rejeté(e).`;
          this.loadParticipants();
          setTimeout(() => this.success = null, 3000);
        }
      },
      error: (err) => {
        this.error = err.error?.message || 'Erreur lors du rejet.';
        setTimeout(() => this.error = null, 3000);
      }
    });
  }

  deleteParticipant(participant: Participant): void {
    if (!confirm(`Supprimer définitivement ${participant.first_name} ${participant.last_name} ? Cette action est irréversible.`)) {
      return;
    }

    this.apiService.deleteParticipant(participant.id).subscribe({
      next: (response) => {
        if (response.ok) {
          this.success = `${participant.first_name} ${participant.last_name} a été supprimé(e).`;
          this.loadParticipants();
          setTimeout(() => this.success = null, 3000);
        }
      },
      error: (err) => {
        this.error = err.error?.message || 'Erreur lors de la suppression.';
        setTimeout(() => this.error = null, 3000);
      }
    });
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
      error: (err) => {
        this.error = 'Erreur lors du téléchargement du badge.';
        setTimeout(() => this.error = null, 3000);
      }
    });
  }

  getStatusBadgeClass(status: string): string {
    switch (status) {
      case 'accepted':
        return 'badge-accepted';
      case 'rejected':
        return 'badge-rejected';
      case 'pending':
      default:
        return 'badge-pending';
    }
  }

  getStatusLabel(status: string): string {
    switch (status) {
      case 'accepted':
        return 'Accepté';
      case 'rejected':
        return 'Rejeté';
      case 'pending':
      default:
        return 'En attente';
    }
  }

  getAccessTypeLabel(accessType: string): string {
    switch (accessType) {
      case 'foire':
        return 'Foire';
      case 'conference':
        return 'Conférence';
      case 'both':
        return 'Foire + Conférence';
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
}
