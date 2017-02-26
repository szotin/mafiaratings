using System;
using System.Collections.Generic;
using System.Text;

namespace MafiaRatings
{
    public struct TimeMeasure
    {
        private static long mFrequency = 0;
        private long mCount;

        public void Start()
        {
            Native.QueryPerformanceCounter(ref mCount);
        }

        public void Stop()
        {
            long count = 0;
            Native.QueryPerformanceCounter(ref count);
            mCount = count - mCount;
        }

        public static long Frequency
        {
            get
            {
                if (mFrequency <= 0)
                {
                    Native.QueryPerformanceFrequency(ref mFrequency);
                }
                return mFrequency;
            }
        }

        public long Count
        {
            get { return mCount; }
        }

        public float Microseconds
        {
            get
            {
                long freq = Frequency;
                if (freq <= 0)
                {
                    return 0.0f;
                }
                return (float)(mCount * 1000000) / (float)freq;
            }
        }

        public float Milliseconds
        {
            get
            {
                long freq = Frequency;
                if (freq <= 0)
                {
                    return 0.0f;
                }
                return (float)(mCount * 1000) / (float)freq;
            }
        }

        public float Seconds
        {
            get
            {
                long freq = Frequency;
                if (freq <= 0)
                {
                    return 0.0f;
                }
                return (float)mCount / (float)freq;
            }
        }

        public override string ToString()
        {
            StringBuilder builder = new StringBuilder();
            long freq = Frequency;
            if (freq <= 0)
            {
                return builder.ToString();
            }
            long milliseconds = (mCount * 1000) / freq;
            int digits = 0;

            if (milliseconds >= 60 * 60 * 1000)
            {
                long hours = milliseconds / (60 * 60 * 1000);
                builder.Append(hours);
                builder.Append('h');
                if (hours >= 100)
                {
                    digits = 3;
                }
                else if (hours >= 10)
                {
                    digits = 2;
                }
                else
                {
                    digits = 1;
                }
                milliseconds -= hours * 60 * 60 * 1000;
            }

            if (digits > 2)
            {
                return builder.ToString();
            }
            else if (digits > 0)
            {
                builder.Append(' ');
            }

            if (milliseconds >= 60 * 1000)
            {
                long minutes = milliseconds / (60 * 1000);
                builder.Append(minutes);
                builder.Append('m');
                if (minutes >= 10)
                {
                    digits += 2;
                }
                else
                {
                    ++digits;
                }
                milliseconds -= minutes * 60 * 1000;
            }

            if (digits > 2)
            {
                return builder.ToString();
            }
            else if (digits > 0)
            {
                builder.Append(' ');
            }

            long seconds = milliseconds / 1000;
            builder.Append(seconds);
            if (seconds >= 10)
            {
                digits += 2;
            }
            else
            {
                ++digits;
            }
            milliseconds -= seconds * 1000;

            if (digits < 2)
            {
                builder.Append('.');
                builder.Append(string.Format("{0:000}", milliseconds));
            }
            builder.Append('s');
            return builder.ToString();
        }
    }
}
