import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ObsControllerComponent } from './obs-controller.component';

describe('ControllerComponent', () => {
  let component: ObsControllerComponent;
  let fixture: ComponentFixture<ObsControllerComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ ObsControllerComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ObsControllerComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
