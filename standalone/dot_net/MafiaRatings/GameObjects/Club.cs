using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class Club
    {
        private int mId;
        private string mName;
        private int mRulesId;

        public Club()
        {
            mId = 0;
            mName = Strings.NoClub;
        }

        public Club(DataReader reader, int version)
        {
            mId = reader.NextInt();
            mName = reader.NextString();
            if (version > 1)
            {
                mRulesId = reader.NextInt();
            }
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mId);
            writer.Add(mName);
            writer.Add(mRulesId);
        }

        public int Id
        {
            get { return mId; }
        }

        public string Name
        {
            get { return mName; }
        }

        public override string ToString()
        {
            return mName;
        }

        public int RulesId
        {
            get { return mRulesId; }
        }
    }
}
