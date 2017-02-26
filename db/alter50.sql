use mafia;

UPDATE user_clubs SET flags = (flags & 63) + ((flags & 192) >> 2);

UPDATE users SET flags = (flags & 159) + ((flags & 4096) >> 7) + ((flags & 32) << 1) + ((flags & 3584) >> 1) + ((flags & 256) << 3);

UPDATE addresses SET flags = (flags >> 1) + ((flags & 1) << 2);