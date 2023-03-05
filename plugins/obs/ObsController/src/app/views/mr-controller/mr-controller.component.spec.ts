import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MrControllerComponent } from './mr-controller.component';

describe('MrControllerComponent', () => {
  let component: MrControllerComponent;
  let fixture: ComponentFixture<MrControllerComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ MrControllerComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MrControllerComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
