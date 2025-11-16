import { Component, OnInit, AfterViewInit, OnDestroy, ViewChild, ElementRef, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ScanBroadcastService, ScanResult } from '../../services/scan-broadcast.service';

@Component({
  selector: 'app-avatar-page',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './avatar-page.component.html',
  styleUrls: ['./avatar-page.component.css']
})
export class AvatarPageComponent implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('silentVideo') silentVideo!: ElementRef<HTMLVideoElement>;
  @ViewChild('speakingVideo') speakingVideo!: ElementRef<HTMLVideoElement>;

  participantName = '';
  gender = 'Female';
  accessStatus = 'Accepted';
  accessType = 'fair';
  alreadyScanned = false;
  isSpeaking = false;
  error: string | null = null;

  private synth = window.speechSynthesis;
  private voicesLoaded = false;
  private userInteracted = false;
  private sub: any;

  constructor(
      private broadcaster: ScanBroadcastService,
      private ngZone: NgZone
  ) {
    // Enable audio on first click
    const enable = () => {
      this.userInteracted = true;
      document.body.removeEventListener('click', enable);
    };
    document.body.addEventListener('click', enable, { once: true });
  }

  ngOnInit() {
    const load = () => {
      if (this.synth.getVoices().length) this.voicesLoaded = true;
      else setTimeout(load, 300);
    };
    load();
    this.synth.onvoiceschanged = load;
  }

  ngAfterViewInit() {
    this.playSilent();
    this.sub = this.broadcaster.getFairScan$().subscribe(scan => {
      this.handleScan(scan);
    });
  }

  ngOnDestroy() {
    if (this.sub) this.sub.unsubscribe();
    this.synth.cancel();
  }

  private handleScan(scan: ScanResult) {
    const p = scan.participant;
    this.ngZone.run(() => {
      if (!p?.first_name) {
        this.participantName = '';
      } else {
        this.participantName = `${p.first_name} ${p.last_name}`.trim();
        this.gender = p.gender || 'Female';
        this.accessType = p.access_type || 'fair';
        this.alreadyScanned = scan.is_already_scanned;
      }
    });

    // Fix NG0100: defer to next tick
    Promise.resolve().then(() => this.playMessage());
  }

  private playMessage() {
    if (!this.userInteracted || this.isSpeaking) return;

    let msg = '';
    if (!this.participantName) {
      msg = "Scan not recognized.";
    } else if (this.alreadyScanned) {
      msg = `Dear ${this.honorific()}, ${this.participantName}, fair access already granted.`;
    } else {
      msg = `Dear ${this.honorific()}, ${this.participantName}, welcome to the fair!`;
    }

    this.speak(msg);
  }

  private honorific() {
    return this.gender === 'Male' ? 'Mr.' : 'Ms.';
  }

  private speak(text: string) {
    this.switchToSpeaking();

    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'en-US';
    u.rate = 0.95;

    // Female voice
    const voices = this.synth.getVoices();
    const female = voices.find(v =>
        v.lang.startsWith('en') &&
        (v.name.toLowerCase().includes('samantha') || v.name.toLowerCase().includes('zira'))
    );
    if (female) u.voice = female;

    u.onend = () => {
      this.ngZone.run(() => {
        this.isSpeaking = false;
        this.playSilent();
      });
    };

    this.synth.speak(u);
  }

  private switchToSpeaking() {
    this.silentVideo?.nativeElement.pause();
    const v = this.speakingVideo?.nativeElement;
    if (v) {
      v.currentTime = 0;
      v.play().catch(() => {});
    }
    this.isSpeaking = true;
  }
  getAccessTypeDisplay(): string {
    switch (this.accessType) {
      case 'fair': return 'Fair only';
      case 'conference': return 'Conference only';
      case 'fair + conference': return 'Fair and Conference';
      default: return '';
    }
  }

  private playSilent() {
    this.isSpeaking = false;
    const v = this.silentVideo?.nativeElement;
    if (v) {
      v.play().catch(() => {});
    }
  }
  onVideoError() {
    this.error = 'Video error.';
  }
}