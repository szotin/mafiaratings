import { TestBed } from '@angular/core/testing';

import { ObsAuthService } from './obs-auth.service';

describe('ObsAuthService', () => {
  let service: ObsAuthService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ObsAuthService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
