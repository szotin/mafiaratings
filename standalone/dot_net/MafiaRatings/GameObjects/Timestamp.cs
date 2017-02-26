using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    static class Timestamp
    {
        public static DateTime Convert(int timestamp)
        {
            DateTime origin = new DateTime(1970, 1, 1, 0, 0, 0, 0, DateTimeKind.Utc);
            origin = origin.AddSeconds(timestamp);
            origin = origin.ToLocalTime();
            return origin;
        }

        public static int Convert(DateTime date)
        {
            date = date.ToUniversalTime();
            DateTime origin = new DateTime(1970, 1, 1, 0, 0, 0, 0, DateTimeKind.Utc);
            TimeSpan diff = date - origin;
            return (int)diff.TotalSeconds;
        }

        public static int Now
        {
            get { return Convert(DateTime.Now); }
        }
    }
}
