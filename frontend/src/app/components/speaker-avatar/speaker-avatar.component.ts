import {
  Component,
  Input,
  OnInit,
  OnDestroy,
  ViewChild,
  ElementRef,
  NgZone
} from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-speaker-avatar',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './speaker-avatar.component.html',
  styleUrls: ['./speaker-avatar.component.css']
})
export class SpeakerAvatarComponent implements OnInit, OnDestroy {
  @ViewChild('silentVideo', { static: false }) silentVideo!: ElementRef<HTMLVideoElement>;
  @ViewChild('speakingVideo', { static: false }) speakingVideo!: ElementRef<HTMLVideoElement>;

  @Input() participantName: string = '';
  @Input() gender: string = 'Femme';
  @Input() autoPlay: boolean = true;
  @Input() accessStatus: string = 'Accepted'; // 'Accepted' or 'Denied'
  @Input() accessType: string = 'both'; // 'foire', 'conference', 'both'

  private synth: SpeechSynthesis;
  private utterance: SpeechSynthesisUtterance | null = null;
  private voicesLoaded = false;
  private retryTimeout: any;

  isPlaying = false;
  isSpeaking = false;
  videoReady = false;
  error: string | null = null;

  private englishVoices: SpeechSynthesisVoice[] = [];
  private selectedVoice: SpeechSynthesisVoice | null = null;

  constructor(private ngZone: NgZone) {
    this.synth = window.speechSynthesis;
  }

  ngOnInit() {
    this.initVoices();

    // Start silent video
    if (this.silentVideo) {
      this.silentVideo.nativeElement.play().catch(e =>
        console.warn('Silent video play failed:', e)
      );
    }

    // Auto start welcome if participant is set
    if (this.autoPlay && this.participantName) {
      setTimeout(() => {
        if (document.hasFocus()) {
          this.playWelcomeMessage();
        } else {
          const handler = () => {
            this.playWelcomeMessage();
            document.removeEventListener('click', handler);
          };
          document.addEventListener('click', handler);
        }
      }, 800);
    }
  }

  ngOnDestroy() {
    this.stopSpeaking();
    if (this.retryTimeout) clearTimeout(this.retryTimeout);
  }

  /** Initialize and load English voices */
  private initVoices() {
    this.loadVoices();

    if (typeof speechSynthesis !== 'undefined') {
      speechSynthesis.onvoiceschanged = () => {
        this.loadVoices();
      };
    }
  }

  /** Load and select English female voice */
  private loadVoices() {
    const voices = this.synth.getVoices();

    if (!voices.length) {
      setTimeout(() => this.loadVoices(), 300);
      return;
    }

    this.englishVoices = voices.filter(v =>
      v.lang.toLowerCase().startsWith('en')
    );

    this.selectedVoice =
      this.englishVoices.find(v =>
        ['female', 'woman', 'zira', 'samantha', 'google uk english female', 'google us english'].some(k =>
          v.name.toLowerCase().includes(k)
        )
      ) || this.englishVoices[0] || voices[0];

    this.voicesLoaded = true;

    console.log('Loaded voices:', this.englishVoices.map(v => v.name));
    console.log('Selected voice:', this.selectedVoice?.name);
  }

  /** Play personalized welcome message */
  async playWelcomeMessage() {
    if (!this.participantName) {
      this.error = 'No participant name provided.';
      return;
    }

    if (!this.voicesLoaded) {
      await new Promise(resolve => setTimeout(resolve, 800));
    }

    const message = this.generateWelcomeMessage();
    await this.speak(message);
  }

  /** Build English welcome message */
  private generateWelcomeMessage(): string {
    const fullName = this.participantName.trim();
    const honorific = this.getHonorific();

    const statusMessage =
      this.accessStatus === 'Accepted'
        ? 'Access granted. Welcome to our event.'
        : 'Access denied. Please contact the administration.';

    const accessTypeMessage = this.getAccessTypeMessage();
    const qrMessage =
      this.accessStatus === 'Accepted'
        ? 'Your QR code has been successfully scanned.'
        : '';

    return `Dear ${honorific} ${fullName}, ${statusMessage} ${accessTypeMessage} ${qrMessage}`.trim();
  }

  private getHonorific(): string {
    if (this.gender === 'Homme') return 'Mr.';
    if (this.gender === 'Femme') return 'Ms.';
    return '';
  }

  private getAccessTypeMessage(): string {
    switch (this.accessType) {
      case 'foire':
        return 'You have access to the fair only.';
      case 'conference':
        return 'You have access to the conference only.';
      case 'both':
        return 'You have access to both the fair and the conference.';
      default:
        return '';
    }
  }

  /** Speak using selected English voice */
  private async speak(text: string) {
    if (!('speechSynthesis' in window)) {
      this.error = 'Speech synthesis not supported.';
      return;
    }

    if (this.synth.speaking) {
      this.synth.cancel();
      await new Promise(r => setTimeout(r, 150));
    }

    this.utterance = new SpeechSynthesisUtterance(text);
    this.utterance.lang = 'en-US';
    this.utterance.rate = 0.95;
    this.utterance.pitch = 1.05;
    this.utterance.volume = 1.0;

    if (this.selectedVoice) this.utterance.voice = this.selectedVoice;

    this.utterance.onstart = () => {
      this.ngZone.run(() => {
        this.isSpeaking = true;
        this.isPlaying = true;
        this.error = null;
        this.switchToSpeakingAvatar();
      });
    };

    this.utterance.onend = () => {
      this.ngZone.run(() => {
        this.isSpeaking = false;
        this.isPlaying = false;
        this.switchToSilentAvatar();
      });
    };

    this.utterance.onerror = (event) => {
      console.error('TTS error:', event);
      this.isSpeaking = false;
      this.isPlaying = false;
      if (event.error === 'interrupted') {
        this.retryTimeout = setTimeout(() => this.speak(text), 800);
      } else {
        this.error = 'TTS error.';
      }
    };

    this.synth.speak(this.utterance);
  }

  /** Avatar video switching */
  private switchToSpeakingAvatar() {
    const silent = this.silentVideo?.nativeElement;
    const speaking = this.speakingVideo?.nativeElement;

    if (silent) silent.pause();
    if (speaking) {
      speaking.currentTime = 0;
      speaking.play().catch(e => console.warn('Speaking video play failed:', e));
    }
  }

  private switchToSilentAvatar() {
    const silent = this.silentVideo?.nativeElement;
    const speaking = this.speakingVideo?.nativeElement;

    if (speaking) speaking.pause();
    if (silent) {
      silent.play().catch(e => console.warn('Silent video resume failed:', e));
    }
  }

  stopSpeaking() {
    if (this.synth.speaking) {
      this.synth.cancel();
    }
    this.isSpeaking = false;
    this.isPlaying = false;
  }

  replay() {
    this.playWelcomeMessage();
  }

  getAccessTypeDisplay(): string {
    switch (this.accessType) {
      case 'foire':
        return 'Fair only';
      case 'conference':
        return 'Conference only';
      case 'both':
        return 'Fair and Conference';
      default:
        return '';
    }
  }

  onVideoReady() {
    this.videoReady = true;
  }

  onVideoError() {
    this.error = 'Video error.';
  }
}
