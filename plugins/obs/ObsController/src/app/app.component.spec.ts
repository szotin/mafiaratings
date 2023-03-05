import { TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { ObsComponent } from './app.component';

describe('ObsComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [
        RouterTestingModule
      ],
      declarations: [
        ObsComponent
      ],
    }).compileComponents();
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(ObsComponent);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  it(`should have as title 'pst-obs'`, () => {
    const fixture = TestBed.createComponent(ObsComponent);
    const app = fixture.componentInstance;
    expect(app.title).toEqual('pst-obs');
  });

  it('should render title', () => {
    const fixture = TestBed.createComponent(ObsComponent);
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('.content span')?.textContent).toContain('pst-obs app is running!');
  });
});
