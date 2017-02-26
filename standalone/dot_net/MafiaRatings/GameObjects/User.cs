using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Collections;

namespace MafiaRatings.GameObjects
{
    public class User : IComparable<User>
    {
        private int mId;
        private string mName;
        private UserNick[] mNicks;
        private int mFlags;

        private const int PERM_PLAYER = 0x1;
        private const int PERM_MODER = 0x2;
        private const int FLAG_MALE = 0x4;
        private const int FLAG_IMMUNITY = 0x8;
        private const int FLAG_CLUB_MEMBER = 0x10;

        public const int FEMALE = 0;
        public const int MALE = 1;

        public User()
        {
            mId = 0;
            mName = Strings.Dummy;
            mFlags = FLAG_MALE | PERM_PLAYER;
            mNicks = new UserNick[0];
        }

        public User(DataReader reader, int version = Database.VERSION)
        {
            mId = reader.NextInt();
            mName = reader.NextString();
            mFlags = reader.NextInt();

            int nicksCount = reader.NextInt();
            mNicks = new UserNick[nicksCount];
            for (int i = 0; i < nicksCount; ++i)
            {
                mNicks[i] = new UserNick(reader, version);
            }
            Array.Sort(mNicks);
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mId);
            writer.Add(mName);
            writer.Add(mFlags);
            writer.Add(mNicks.Length);
            for (int i = 0; i < mNicks.Length; ++i)
            {
                mNicks[i].Write(writer);
            }
        }

        public int CompareTo(User user)
        {
            return mName.CompareTo(user.mName);
        }

        public int Id
        {
            get { return mId; }
        }

        public string Name
        {
            get { return mName; }
        }

        public int NicksCount
        {
            get { return mNicks.Length; }
        }

        public IEnumerable<UserNick> Nicks
        {
            get { return mNicks; }
        }

        public bool IsMale
        {
            get { return (mFlags & FLAG_MALE) != 0; }
        }

        public bool HasImmunity
        {
            get { return (mFlags & FLAG_IMMUNITY) != 0; }
            set
            {
                if (value)
                {
                    mFlags |= FLAG_IMMUNITY;
                }
                else
                {
                    mFlags &= ~FLAG_IMMUNITY;
                }
            }
        }

        public bool IsClubMember
        {
            get { return (mFlags & FLAG_CLUB_MEMBER) != 0; }
            set
            {
                if (value)
                {
                    mFlags |= FLAG_CLUB_MEMBER;
                }
                else
                {
                    mFlags &= ~FLAG_CLUB_MEMBER;
                }
            }
        }

        public bool IsDummy
        {
            get { return mId == 0; }
        }

        public bool IsPlayer
        {
            get { return (mFlags & PERM_PLAYER) != 0; }
        }

        public bool IsModer
        {
            get { return (mFlags & PERM_MODER) != 0; }
        }

        public override string ToString()
        {
 	         return mName;
        }
    }
}
