import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

@Component({
  selector: 'app-badge',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './badge.component.html',
  styleUrls: ['./badge.component.css']
})
export class BadgeComponent implements OnInit {
  qrCode: string | null = null;
  participantName: string | null = null;
  emailSent = true;

  constructor(private router: Router) {}

  ngOnInit(): void {
     console.log('🎉 BadgeComponent initialized');
    console.log('📥 Navigation state:', history.state);
    const navigation = this.router.getCurrentNavigation();
    if (navigation?.extras?.state) {
      this.qrCode = navigation.extras.state['qrCode'];
      this.participantName = navigation.extras.state['participantName'];
      this.emailSent = navigation.extras.state['emailSent'] ?? true;
    }

    if (!this.qrCode) {
      // If no data, redirect to register
      this.router.navigate(['/register']);
    }
  }

  downloadQR(): void {
    if (!this.qrCode) return;

    const link = document.createElement('a');
    link.href = `data:image/png;base64,${this.qrCode}`;
    link.download = `qr-code-${Date.now()}.png`;
    link.click();
  }

  printQR(): void {
    if (!this.qrCode) return;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(`
        <html>
          <head>
            <title>Badge - Event Access</title>
            <style>
              @media print {
                body { margin: 0; }
                .badge-container { page-break-inside: avoid; }
              }
              body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
              .badge-container {
                border: 2px solid #2563eb;
                border-radius: 10px;
                padding: 20px;
                max-width: 400px;
                margin: 0 auto;
                text-align: center;
                background: white;
              }
              .header { color: #2563eb; margin-bottom: 20px; }
              .participant-name { font-size: 18px; font-weight: bold; margin: 10px 0; }
              .qr-code { margin: 20px 0; }
              .qr-code img { max-width: 200px; height: auto; }
              .instructions { font-size: 12px; color: #666; margin-top: 15px; }
              .footer { margin-top: 20px; font-size: 10px; color: #999; }
            </style>
          </head>
          <body>
            <div class="badge-container">
              <div class="header">
                <h1>Event Access</h1>
                <h2>Badge d'Accès</h2>
              </div>
              <div class="participant-name">${this.participantName}</div>
              <div class="qr-code">
                <img src="data:image/png;base64,${this.qrCode}" alt="QR Code" />
              </div>
              <div class="instructions">
                Présentez ce QR code à l'entrée de l'événement
              </div>
              <div class="footer">
                Généré le ${new Date().toLocaleDateString('fr-FR')}
              </div>
            </div>
          </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.print();
    }
  }

  registerAnother(): void {
    this.router.navigate(['/register']);
  }
}
