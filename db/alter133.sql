UPDATE events SET flags = (flags | 512) & ~16 WHERE name IN ('финал', 'final', 'полуфинал', 'semi-final');
UPDATE tournaments SET flags = flags & ~128 WHERE id IN (SELECT tournament_id FROM events WHERE (flags & 512) <> 0);
