import { TestBed } from '@angular/core/testing';

import { LoggedInGuardService } from './logged-in-guard.service';

describe('LoggedInGuardService', () => {
  let service: LoggedInGuardService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(LoggedInGuardService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
