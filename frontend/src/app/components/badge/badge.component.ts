import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {Router, RouterLink} from '@angular/router';

@Component({
  selector: 'app-badge',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './badge.component.html',
  styleUrls: ['./badge.component.css']
})
export class BadgeComponent implements OnInit {
  qrCode: string | null = null;
  participantName: string | null = null;
  emailSent = true;

  constructor(private router: Router) {}

ngOnInit(): void {
  const state = history.state;
  if (state?.qrCode) {
    this.qrCode = state.qrCode;
    this.participantName = state.participantName;
    this.emailSent = state.emailSent ?? true;
  } else {
    console.error('No QR code in navigation state — redirecting to register');
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
              body {
                margin: 0;
                padding: 0;
                size: A4; /* or letter */
                font-size: 12pt;
              }
              .badge-container {
                width: 210mm; /* A4 width */
                height: 297mm; /* A4 height */
                margin: 0 auto;
                padding: 20mm;
                box-sizing: border-box;
                border: 2px solid #2563eb;
                border-radius: 10mm;
                background: white;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                page-break-inside: avoid;
                break-inside: avoid;
                position: relative;
              }
              .header {
                color: #2563eb;
                margin-bottom: 10mm;
              }
              .participant-name {
                font-size: 18pt;
                font-weight: bold;
                margin: 5mm 0;
              }
              .qr-code {
                margin: 10mm 0;
              }
              .qr-code img {
                max-width: 80mm;
                height: auto;
              }
              .instructions {
                font-size: 10pt;
                color: #666;
                margin-top: 5mm;
                text-align: center;
              }
              .footer {
                position: absolute;
                bottom: 10mm;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 8pt;
                color: #999;
              }
              /* Prevent any element from causing page break */
              * {
                page-break-before: avoid !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                break-before: avoid !important;
                break-after: avoid !important;
                break-inside: avoid !important;
              }
            }

            /* For screen preview only */
            @media screen {
              body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f9fafb; }
              .badge-container {
                max-width: 400px;
                margin: 0 auto;
                padding: 20px;
                border: 2px solid #2563eb;
                border-radius: 10px;
                background: white;
                text-align: center;
              }
              .header { color: #2563eb; margin-bottom: 20px; }
              .participant-name { font-size: 18px; font-weight: bold; margin: 10px 0; }
              .qr-code { margin: 20px 0; }
              .qr-code img { max-width: 200px; height: auto; }
              .instructions { font-size: 12px; color: #666; margin-top: 15px; }
              .footer { margin-top: 20px; font-size: 10px; color: #999; }
            }
          </style>
        </head>
        <body>
          <div class="badge-container">
            <div class="header">
             
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
