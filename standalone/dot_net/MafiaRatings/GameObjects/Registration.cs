using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class Registration : IComparable<Registration>
    {
        private int mUserId;
        private int mPartyId;
        private string mNickname;
        private int mStartTime;
        private int mDuration;

        public Registration(Party party) // dummy player
        {
            mUserId = 0;
            mPartyId = party.Id;
            mNickname = Strings.Dummy;
            mStartTime = 0;
            mDuration = int.MaxValue;
        }

        public Registration(Party party, int userId, string nickName, int duration)
        {
            mUserId = userId;
            mPartyId = party.Id;
            mNickname = nickName;
            mStartTime = 0;
            mDuration = duration;
        }

        public Registration(Party party, DataReader reader, int version = Database.VERSION)
        {
            mUserId = reader.NextInt();
            mPartyId = party.Id;
            mNickname = reader.NextString();
            if (party.Id <= 0)
            {
                mStartTime = reader.NextInt();
                mDuration = reader.NextInt();
            }
            else
            {
                mStartTime = party.StartTime;
                mDuration = party.Duration;
            }
        }

        public void Write(DataWriter writer)
        {
            Party party = Database.Parties[mPartyId];

            writer.Add(mUserId);
            writer.Add(mNickname);
            if (party.Id <= 0)
            {
                writer.Add(mStartTime);
                writer.Add(mDuration);
            }
        }

        public int CompareTo(Registration other)
        {
            if (mUserId <= 0)
            {
                if (other.mUserId <= 0)
                {
                    return 0;
                }
                else
                {
                    return -1;
                }
            }
            else if (other.mUserId <= 0)
            {
                return 1;
            }

            return mNickname.CompareTo(other.mNickname);
        }

        public int UserId
        {
            get { return mUserId; }
        }

        public User User
        {
            get { return Database.Users[mUserId]; }
        }

        public int PartyId
        {
            get { return mPartyId; }
        }

        public Party Party
        {
            get { return Database.Parties[mPartyId]; }
        }

        public string Nickname
        {
            get { return mNickname; }
        }

        public int StartTime
        {
            get { return mStartTime; }
        }

        public int Duration
        {
            get { return mDuration; }
        }

        public bool IsDummy
        {
            get { return mUserId <= 0; }
        }

        public override string ToString()
        {
            return mNickname;
        }

        public bool CanPlay
        {
            get { return User.IsPlayer && (Party.AllCanModerate || mUserId != Database.UserId); }
        }

        public bool CanModerate
        {
            get { return Party.AllCanModerate && !IsDummy; }
        }
    }
}
