using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class UserNick : IComparable<UserNick>
    {
        private string mNick;
        private int mUseCount;

        public UserNick(string nick)
        {
            mNick = nick;
            mUseCount = 0;
        }

        public UserNick(DataReader reader, int version = Database.VERSION)
        {
            mNick = reader.NextString();
            mUseCount = reader.NextInt();
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mNick);
            writer.Add(mUseCount);
        }

        public int UseCount
        {
            get { return mUseCount; }
        }

        public string Nick
        {
            get { return mNick; }
        }

        public int CompareTo(UserNick other)
        {
            return other.mUseCount.CompareTo(mUseCount);
        }

        public override string ToString()
        {
            return mNick;
        }
    }
}
