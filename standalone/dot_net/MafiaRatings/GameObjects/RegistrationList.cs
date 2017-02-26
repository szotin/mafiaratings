using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Collections;

namespace MafiaRatings.GameObjects
{
    public class RegistrationList : IEnumerable
    {
        private int mPartyId;
        private Dictionary<int, Registration> mRegistrations = new Dictionary<int, Registration>();

        public RegistrationList(Party party)
        {
            mPartyId = party.Id;
            mRegistrations.Add(0, new Registration(party)); // dummy player registration
        }

        public RegistrationList(Party party, DataReader reader, int version = Database.VERSION) : this(party)
        {
            int regCount = reader.NextInt();
            for (int i = 0; i < regCount; ++i)
            {
                Registration reg = new Registration(party, reader, version);
                if (Database.Users.Contains(reg.UserId))
                {
                    mRegistrations.Add(reg.UserId, reg);
                }
            }
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mRegistrations.Count - 1);
            foreach (Registration reg in mRegistrations.Values)
            {
                if (!reg.IsDummy)
                {
                    reg.Write(writer);
                }
            }
        }

        public Registration this[int userId]
        {
            get
            {
                Registration reg = null;
                mRegistrations.TryGetValue(userId, out reg);
                return reg;
            }
        }

        public bool Contains(int userId)
        {
            return mRegistrations.ContainsKey(userId);
        }

        public IEnumerator GetEnumerator()
        {
            return mRegistrations.Values.GetEnumerator();
        }

        public int PartyId
        {
            get { return mPartyId; }
        }

        public int Count
        {
            get { return mRegistrations.Count; }
        }

        public void Add(Registration reg)
        {
            if (mRegistrations.ContainsKey(reg.UserId))
            {
                throw new GameException(string.Format(Strings.ErrUserRegistered, reg.User.Name));
            }
            mRegistrations.Add(reg.UserId, reg);
        }
    }
}
