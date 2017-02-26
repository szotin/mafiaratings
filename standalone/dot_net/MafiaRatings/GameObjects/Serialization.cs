using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class DataWriter
    {
        private StringBuilder mData;
        private char mSeparator;

        public DataWriter(char separator)
        {
            mData = new StringBuilder();
            mSeparator = separator;
        }

        public void Add(string item)
        {
            mData.Append(item);
            mData.Append(mSeparator);
        }

        public void Add(int item)
        {
            mData.Append(item.ToString());
            mData.Append(mSeparator);
        }

        public void Add(bool item)
        {
            mData.Append(item ? '1' : '0');
            mData.Append(mSeparator);
        }

        public override string ToString()
        {
            return mData.ToString();
        }

        protected void Write(string str)
        {
            mData.Append(str);
        }

        public string Data
        {
            get { return mData.ToString(); }
        }

        public char Separator
        {
            get { return mSeparator; }
        }

        protected StringBuilder DataBuilder
        {
            get { return mData; }
        }
    }

    public class DataReader
    {
        private string mData;
        private char mSeparator;
        private int mOffset;

        public DataReader(string data, char separator)
        {
            mData = data;
            mSeparator = separator;
            mOffset = 0;
        }

        public bool HasNext()
        {
            return mData.IndexOf(mSeparator, mOffset) >= 0;
        }

        public string NextString()
        {
            int nextOffset = mData.IndexOf(mSeparator, mOffset);
            if (nextOffset < 0)
            {
                throw new GameException(Strings.ErrInvalidData);
            }

            string result = mData.Substring(mOffset, nextOffset - mOffset);
            mOffset = nextOffset + 1;
            return result;
        }

        public int NextInt()
        {
            try
            {
                return Convert.ToInt32(NextString());
            }
            catch (Exception)
            {
                throw new GameException(Strings.ErrInvalidData);
            }
        }

        public bool NextBool()
        {
            string str = NextString();
            if (str == "0")
            {
                return false;
            }
            else if (str == "1")
            {
                return true;
            }
            throw new GameException(Strings.ErrInvalidData);
        }

        public override string ToString()
        {
            return mData;
        }

        public string Data
        {
            get { return mData; }
        }

        public char Separator
        {
            get { return mSeparator; }
        }
    }
}
